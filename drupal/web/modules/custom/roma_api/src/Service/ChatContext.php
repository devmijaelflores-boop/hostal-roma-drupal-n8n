<?php
namespace Drupal\roma_api\Service;

/** Contexto acotado: solo fechas y ocupación, nunca transcripciones ni PII. */
final class ChatContext {
  public static function prepare(string $input, array $saved, string $today, int $now): array {
    $state = (($saved['updated'] ?? 0) > $now - 1800) ? array_intersect_key($saved, array_flip(['updated','arrival','departure','guests','active'])) : [];
    $text = strtr(mb_strtolower(trim($input)), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    $result = static fn(array $context, string $message, ?string $reply = NULL): array => ['context'=>$context,'input'=>$message,'reply'=>$reply];
    if (preg_match('/^(?:reiniciar|empezar de nuevo|olvida lo anterior)[.! ]*$/', $text)) { return $result([], $input, 'Listo, empezamos de nuevo 😊. ¿Para cuántas personas y qué fechas buscas habitación?'); }
    $hotel = (bool) preg_match('/habitacion|individual|matrimonial|doble|precio|tarifa|presupuesto|cuesta|reserva|alojar|disponib|estad[ií]a/', $text);
    $follow = (bool) preg_match('/^(?:(?:y|pues|ok|si)[, ]+)?(?:somos|viajamos|para|del? |lleg|ingres|salim|salgo|salida|hasta|nos vamos|una? noche|dos noches|tres noches|cuatro noches|\d{1,2}\s*(?:personas|huespedes|noches)|mañana|manana)/u', $text);
    $follow = $follow || (bool) preg_match('/^(?:\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|\d{1,2})$/',$text);
    if (!$hotel && !(($state['active'] ?? FALSE) && $follow)) { return $result($state, $input); }
    $state['active'] = TRUE; $state['updated'] = $now;
    $words = ['un'=>1,'uno'=>1,'una'=>1,'dos'=>2,'tres'=>3,'cuatro'=>4,'cinco'=>5,'seis'=>6,'siete'=>7,'ocho'=>8,'nueve'=>9,'diez'=>10];
    if (preg_match('/(?:somos|viajamos|para)\s+(\d{1,2}|uno?|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\b/', $text, $m) || preg_match('/\b(\d{1,2})\s*(?:personas|huespedes|adultos)\b/', $text, $m)) {
      $guests=$words[$m[1]] ?? (int)$m[1];
      if ($guests<1 || $guests>10) { return $result($state,$input,'Indica entre 1 y 10 huéspedes. Para grupos, puedes registrar una solicitud por habitación.'); }
      $state['guests']=$guests;
    }
    if (empty($state['guests']) && preg_match('/^([1-9]|10)$/',$text,$m)) { $state['guests']=(int)$m[1]; }
    preg_match_all('/\b(?:\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4})\b/', $text, $matches);
    $dates=[];
    foreach ($matches[0] as $date) {
      $iso=str_contains($date,'/')?implode('-',array_reverse(explode('/',$date))):$date;
      $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$iso);
      if (!$parsed || $parsed->format('Y-m-d')!==$iso) { return $result($state,$input,'Esa fecha no es válida. Escríbela como día/mes/año, por ejemplo 20/12/2027.'); }
      $dates[]=$iso;
    }
    if (count($dates)>=2) { $state['arrival']=$dates[0]; $state['departure']=$dates[1]; }
    elseif ($dates) {
      if (preg_match('/salida|salim|salgo|hasta|nos vamos/', $text) || (!empty($state['arrival']) && !$hotel && !preg_match('/lleg|ingres|desde/', $text))) { $state['departure']=$dates[0]; }
      else { $state['arrival']=$dates[0]; unset($state['departure']); }
    }
    if (str_contains($text,'manana')) {
      $date=(new \DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
      if (preg_match('/salida|salim|salgo|nos vamos/', $text)) { $state['departure']=$date; }
      else { $state['arrival']=$date; unset($state['departure']); }
    }
    if (preg_match('/\b(\d{1,2}|una?|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s+noches?\b/', $text, $m) && !empty($state['arrival'])) {
      $nights=$words[$m[1]] ?? (int)$m[1];
      if ($nights<1 || $nights>30) { return $result($state,$input,'Indica entre 1 y 30 noches para esta consulta.'); }
      $state['departure']=(new \DateTimeImmutable($state['arrival']))->modify('+'.$nights.' days')->format('Y-m-d');
    }
    // Las preguntas de precio se contestan primero; los seguimientos completan fechas.
    if (!$follow && !$dates && !str_contains($text,'manana') && !preg_match('/disponib/', $text)) { return $result($state,$input); }
    if (empty($state['guests'])) { return $result($state,$input,'¡Vamos viendo opciones! 😊 ¿Cuántas personas viajarían?'); }
    if (empty($state['arrival'])) { return $result($state,$input,'Perfecto, para '.$state['guests'].' persona(s) 😊. ¿Qué día llegarían? Puedes escribir una fecha completa o «mañana».'); }
    if (empty($state['departure'])) { return $result($state,$input,'Anotado: llegada el '.implode('/',array_reverse(explode('-',$state['arrival']))).' para '.$state['guests'].' persona(s). ¿Qué día saldrían o cuántas noches se quedarían?'); }
    return $result($state,'Disponibilidad del '.$state['arrival'].' al '.$state['departure'].' para '.$state['guests'].' personas');
  }
}
