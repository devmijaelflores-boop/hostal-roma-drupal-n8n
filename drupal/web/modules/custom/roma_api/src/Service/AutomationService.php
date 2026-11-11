<?php
namespace Drupal\roma_api\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

/** Entrega solo a Mailpit local: sin salida SMTP hacia personas reales. */
final class AutomationService {
  public function __construct(private readonly Connection $db, private readonly HotelService $hotel, private readonly TimeInterface $time, private readonly LockBackendInterface $lock) {}
  public function enqueue(string $key, int $reservation, string $kind): void {
    if (!$this->db->select('roma_notification','n')->condition('event_key',$key)->countQuery()->execute()->fetchField()) {
      $this->db->insert('roma_notification')->fields(['event_key'=>$key,'reservation_id'=>$reservation,'kind'=>$kind,'state'=>'queued','attempts'=>0,'created'=>$this->time->getCurrentTime(),'delivered'=>0])->execute();
    }
  }
  public function tick(): array {
    if (!$this->lock->acquire('roma_automation_tick', 300)) { return ['busy'=>TRUE]; }
    $result = ['delivered'=>0,'skipped'=>0,'failed'=>0];
    try {
      $today = $this->hotel->today(); $tomorrow = (new \DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
      $ids = $this->db->select('roma_reservation','b')->fields('b',['id'])->condition('status','confirmed')->condition('arrival',$tomorrow)->execute()->fetchCol();
      foreach ($ids as $id) { $this->enqueue('reminder:' . $id . ':' . $tomorrow, (int) $id, 'reminder'); }
      $this->enqueue('report:' . $today, 0, 'daily_report');
      $rows = $this->db->select('roma_notification','n')->fields('n')->condition('state','queued')->condition('attempts',10,'<')->orderBy('id')->range(0,20)->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $mailer = new Mailer(Transport::fromDsn('smtp://mailpit:1025?auto_tls=false&timeout=5'));
      foreach ($rows as $event) {
        try {
          $to = 'admin@example.invalid';
          if ($event['kind'] === 'daily_report') {
            $stats = $this->hotel->stats();
            $subject = 'Resumen diario de prueba · ' . $today;
            $text = 'Pendientes: ' . $stats['pending'] . '. Confirmadas: ' . $stats['confirmed'] . '. Ocupadas hoy: ' . $stats['occupied'] . '. No representa ingresos ni reservas reales.';
          }
          else {
            try { $booking = $this->hotel->reservation((int) $event['reservation_id']); }
            catch (\DomainException) { $this->skip((int) $event['id']); $result['skipped']++; continue; }
            if ($event['kind'] === 'finance') {
              if (!\Drupal::hasService('roma_finance.ledger') || !preg_match('/^finance:(\d+)$/D',$event['event_key'],$match)) { $this->skip((int)$event['id']);$result['skipped']++;continue; }
              $ledger=\Drupal::service('roma_finance.ledger');
              try { $entry=$ledger->entry((int)$match[1]); }
              catch (\DomainException) { $this->skip((int)$event['id']);$result['skipped']++;continue; }
              if ((int)$entry['reservation_id'] !== (int)$booking['id']) { $this->skip((int)$event['id']);$result['skipped']++;continue; }
              $account=$ledger->account((int)$booking['id']);
              $subject='Movimiento financiero simulado · '.$entry['reference'].' · prueba';
              $text='Solicitud: '.$booking['reference'].'. Movimiento: '.\Drupal\roma_finance\Service\LedgerService::KINDS[$entry['kind']].'. Importe: '.HotelService::money((int)$entry['amount']).'. Saldo actual simulado: '.HotelService::money($account['balance']).'. No se realizó un cobro real. No es un comprobante SUNAT.';
            }
            else {
            if ($event['kind'] === 'confirmed' && $booking['status'] !== 'confirmed') { $this->skip((int) $event['id']); $result['skipped']++; continue; }
            if ($event['kind'] === 'reminder' && ($booking['status'] !== 'confirmed' || $booking['arrival'] !== $tomorrow)) { $this->skip((int) $event['id']); $result['skipped']++; continue; }
            $label = $event['kind'] === 'reminder' ? 'Recordatorio de llegada' : (HotelService::STATUS[$event['kind']] ?? 'Aviso');
            $subject = $label . ' · ' . $booking['reference'] . ' · prueba';
            $text = 'Evento: ' . $label . '. Solicitud: ' . $booking['reference'] . '. Estado actual: ' . HotelService::STATUS[$booking['status']] . '. Del ' . HotelService::date($booking['arrival']) . ' al ' . HotelService::date($booking['departure']) . '. Estimado: ' . HotelService::money((int) $booking['estimated_price']) . '. Prototipo académico no oficial. No es una reserva comercial.';
            if ($event['kind'] !== 'pending') { $to = $booking['email']; }
            }
          }
          $mail = (new Email())->from('demo@example.invalid')->to($to)->subject('[CAPTURA LOCAL] ' . $subject)->text($text . "\nAviso capturado por Mailpit. No enviado a Internet.");
          $mail->getHeaders()->addTextHeader('X-ROMA-Event', $event['event_key']);
          $mailer->send($mail);
          $this->db->update('roma_notification')->fields(['state'=>'delivered','delivered'=>$this->time->getCurrentTime(),'attempts'=>(int)$event['attempts']+1])->condition('id',$event['id'])->execute();
          $result['delivered']++;
        }
        catch (\Throwable) {
          $this->db->update('roma_notification')->fields(['attempts'=>(int)$event['attempts']+1])->condition('id',$event['id'])->execute();
          $result['failed']++;
        }
      }
      return $result + ['capture'=>'Mailpit local'];
    }
    finally { $this->lock->release('roma_automation_tick'); }
  }
  private function skip(int $id): void { $this->db->update('roma_notification')->fields(['state'=>'skipped'])->condition('id',$id)->execute(); }
}
