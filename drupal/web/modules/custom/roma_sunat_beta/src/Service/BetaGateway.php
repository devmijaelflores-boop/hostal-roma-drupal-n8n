<?php
namespace Drupal\roma_sunat_beta\Service;

use Greenter\Factory\FeFactory;
use Greenter\Factory\XmlBuilderResolver;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\XMLSecLibs\Sunat\SignedXml;

/** Sin opción de producción ni datos fiscales reales de huéspedes o del hotel. */
class BetaGateway {
  public const ISSUER='20000000001';
  // Huella de la contraseña genérica pública del tutorial; nunca una clave SOL real.
  private const PUBLIC_TEST_PASSWORD_HASH='bb4a5f128236e5cf1ec5e09e82e3b54ab1a106271cf041d315849e0560594231';
  public function endpoint(): string { return SunatEndpoints::FE_BETA; }
  public function ready(): bool { return extension_loaded('soap')&&getenv('ROMA_SUNAT_BETA_USER')==='MODDATOS'&&hash_equals(self::PUBLIC_TEST_PASSWORD_HASH,hash('sha256',getenv('ROMA_SUNAT_BETA_PASSWORD')?:'')); }
  private function certificate(): string {
    $path=getenv('ROMA_SUNAT_PRIVATE_PATH')?:'/var/www/private/sunat-beta';
    if(!str_starts_with($path,'/'))throw new \DomainException('La carpeta de firma debe ser absoluta y privada.');
    if(!is_dir($path)&&!mkdir($path,0700,TRUE)&&!is_dir($path))throw new \RuntimeException('No se pudo crear la carpeta privada de firma.');
    $real=realpath($path);$web=defined('DRUPAL_ROOT')?realpath(DRUPAL_ROOT):NULL;
    if(!$real||($web&&($real===$web||str_starts_with($real,$web.'/'))))throw new \DomainException('No almacenes certificados en la carpeta web.');
    if((fileperms($real)&0077)!==0)throw new \DomainException('La carpeta privada de firma debe tener permisos 700.');
    $file=$real.'/academic-beta.pem';
    if(is_link($file))throw new \DomainException('El certificado académico no puede ser un enlace simbólico.');
    if(!is_file($file)){
      $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
      $csr=$key?openssl_csr_new(['commonName'=>'PROTOTIPO ACADEMICO SUNAT BETA','organizationName'=>'EMISOR FICTICIO DE PRUEBAS'],$key,['digest_alg'=>'sha256']):FALSE;
      $cert=$csr?openssl_csr_sign($csr,NULL,$key,365,['digest_alg'=>'sha256']):FALSE;
      if(!$key||!$cert||!openssl_pkey_export($key,$private)||!openssl_x509_export($cert,$public))throw new \RuntimeException('No se pudo generar la firma académica.');
      $temp=tempnam($real,'key-');if(!$temp)throw new \RuntimeException('No se pudo preparar el certificado.');
      chmod($temp,0600);file_put_contents($temp,$private."\n".$public,LOCK_EX);
      // Drush puede ejecutarse como root; la firma debe seguir siendo legible
      // por el propietario de la carpeta privada (www-data en Docker).
      $owner=fileowner($real);
      if(fileowner($temp)!==$owner&&!chown($temp,$owner)){@unlink($temp);throw new \RuntimeException('No se pudo conservar la propiedad privada del certificado.');}
      if(!rename($temp,$file)){@unlink($temp);throw new \RuntimeException('No se pudo conservar el certificado académico.');}
    }
    if((fileperms($file)&0077)!==0)throw new \DomainException('El certificado académico debe tener permisos 600.');
    $pem=file_get_contents($file);if(!$pem||!openssl_pkey_get_private($pem))throw new \RuntimeException('La firma académica no está disponible.');return $pem;
  }
  public function build(array $s): string {
    if(!in_array($s['type'],['01','03'],TRUE)||$s['series']!==($s['type']==='01'?'F901':'B901')||$s['number']<1||$s['number']>99999999)throw new \DomainException('Datos del documento de prueba inválidos.');
    $gross=(int)$s['amount'];if($gross<1)throw new \DomainException('El total debe ser positivo.');
    // Ejercicio gravado al 18 %, sin determinar el régimen fiscal del hospedaje.
    $base=intdiv($gross*100+59,118);$tax=$gross-$base;
    $address=(new Address())->setUbigueo('150101')->setDepartamento('LIMA')->setProvincia('LIMA')->setDistrito('LIMA')->setDireccion('DOMICILIO FICTICIO DE PRUEBAS S/N')->setCodLocal('0000');
    $company=(new Company())->setRuc(self::ISSUER)->setRazonSocial('EMISOR ACADEMICO DE PRUEBAS')->setNombreComercial('PROTOTIPO NO OFICIAL')->setAddress($address);
    $client=(new Client())->setTipoDoc($s['type']==='01'?'6':'1')->setNumDoc($s['type']==='01'?self::ISSUER:'00000000')->setRznSocial('CLIENTE FICTICIO PARA PRUEBAS UBL');
    $date=(new \DateTime('@'.$s['created']))->setTimezone(new \DateTimeZone('America/Lima'));
    $invoice=(new Invoice())->setUblVersion('2.1')->setTipoOperacion('0101')->setTipoDoc($s['type'])->setSerie($s['series'])->setCorrelativo((string)$s['number'])->setFechaEmision($date)->setFormaPago(new FormaPagoContado())->setTipoMoneda('PEN')->setCompany($company)->setClient($client)->setMtoOperGravadas($base/100)->setMtoIGV($tax/100)->setTotalImpuestos($tax/100)->setValorVenta($base/100)->setSubTotal($gross/100)->setMtoImpVenta($gross/100);
    $item=(new SaleDetail())->setCodProducto('DEMO-ALOJAMIENTO')->setUnidad('ZZ')->setCantidad(1)->setDescripcion('ALOJAMIENTO ACADEMICO DE DEMOSTRACION SIN OPERACION REAL')->setMtoValorUnitario($base/100)->setMtoValorVenta($base/100)->setMtoBaseIgv($base/100)->setPorcentajeIgv(18)->setIgv($tax/100)->setTipAfeIgv('10')->setTotalImpuestos($tax/100)->setMtoPrecioUnitario($gross/100);
    $formatter=new \NumberFormatter('es_PE',\NumberFormatter::SPELLOUT);$words=mb_strtoupper((string)$formatter->format(intdiv($gross,100))).' CON '.sprintf('%02d',$gross%100).'/100 SOLES';
    $invoice->setDetails([$item])->setLegends([(new Legend())->setCode('1000')->setValue($words)]);
    $signer=new SignedXml();$signer->setCertificate($this->certificate());
    $factory=(new FeFactory())->setSigner($signer)->setBuilder((new XmlBuilderResolver(['autoescape'=>'html']))->find(Invoice::class));
    $xml=$factory->getXmlSigned($invoice);if(!$xml||strlen($xml)>1048576||!(new SignedXml())->verifyXml($xml))throw new \RuntimeException('El XML no pasó la comprobación local de firma.');return $xml;
  }
  public static function validateZip(string $content): void {
    if(strlen($content)>2097152)throw new \RuntimeException('Respuesta beta demasiado grande.');
    $temp=tempnam(sys_get_temp_dir(),'roma-cdr-');if(!$temp)throw new \RuntimeException('No se pudo validar el CDR.');
    try{
      chmod($temp,0600);file_put_contents($temp,$content);$zip=new \ZipArchive();if($zip->open($temp)!==TRUE)throw new \RuntimeException('La respuesta no es un ZIP válido.');
      try{
        if($zip->numFiles<1||$zip->numFiles>5)throw new \RuntimeException('Contenido de CDR inesperado.');$total=0;$xmlCount=0;
        for($i=0;$i<$zip->numFiles;$i++){
          $stat=$zip->statIndex($i);$total+=(int)$stat['size'];
          if(str_ends_with($stat['name'],'/')){
            if((int)$stat['size']!==0||!preg_match('/^[A-Za-z0-9_-]+\/$/D',$stat['name']))throw new \RuntimeException('Directorio de CDR no permitido.');
            continue;
          }
          if($total>2097152||$stat['name']!==basename($stat['name'])||str_contains($stat['name'],'\\')||!str_ends_with(strtolower($stat['name']),'.xml'))throw new \RuntimeException('Contenido de CDR no permitido.');
          $xml=$zip->getFromIndex($i);if($xml===FALSE||stripos($xml,'<!DOCTYPE')!==FALSE)throw new \RuntimeException('XML de CDR no permitido.');
          $dom=new \DOMDocument();if(!@$dom->loadXML($xml,LIBXML_NONET))throw new \RuntimeException('CDR con XML inválido.');
          $xmlCount++;
        }
        if($xmlCount!==1)throw new \RuntimeException('El CDR debe contener exactamente un XML.');
      }finally{$zip->close();}
    }finally{@unlink($temp);}
  }
  public function send(string $filename,string $xml): array {
    if(!$this->ready())throw new \DomainException('Configura únicamente las credenciales genéricas beta y la extensión SOAP. No se admiten claves SOL reales.');
    if(!preg_match('/^20000000001-(?:01-F901|03-B901)-[1-9][0-9]{0,7}$/D',$filename)||!(new SignedXml())->verifyXml($xml))throw new \DomainException('Documento de prueba o firma no válidos.');
    $context=stream_context_create(['ssl'=>['verify_peer'=>TRUE,'verify_peer_name'=>TRUE,'allow_self_signed'=>FALSE],'http'=>['timeout'=>25,'follow_location'=>0,'max_redirects'=>0]]);
    // Se reemplazan expresamente las opciones TLS inseguras por defecto del SDK.
    $client=new BetaSoapClient('',['stream_context'=>$context,'connection_timeout'=>10,'trace'=>FALSE,'exceptions'=>TRUE,'keep_alive'=>FALSE,'cache_wsdl'=>WSDL_CACHE_NONE]);
    $client->setService($this->endpoint());$client->setCredentials(self::ISSUER.getenv('ROMA_SUNAT_BETA_USER'),getenv('ROMA_SUNAT_BETA_PASSWORD'));
    $sender=(new BillSender())->setClient($client);$result=$sender->send($filename,$xml);
    if(!$result||!$result->isSuccess()){
      $error=$result?->getError();$code=(string)($error?->getCode()??'TRANSPORT');$description=(string)($error?->getMessage()??'No se obtuvo una respuesta verificable.');
      return ['state'=>ctype_digit($code)?'error':'uncertain','code'=>$code,'description'=>$description,'cdr'=>''];
    }
    return $this->parse($filename,(string)$result->getCdrZip());
  }
  /** Revisa una respuesta ya recibida, sin repetir la solicitud SOAP. */
  public function parse(string $filename,string $zip): array {
    self::validateZip($zip);$temp=tempnam(sys_get_temp_dir(),'roma-cdr-read-');if(!$temp)throw new \RuntimeException('No se pudo revisar el CDR.');
    try{
      chmod($temp,0600);file_put_contents($temp,$zip);$archive=new \ZipArchive();if($archive->open($temp)!==TRUE)throw new \RuntimeException('CDR no válido.');
      try{$xml='';for($i=0;$i<$archive->numFiles;$i++)if(str_ends_with(strtolower($archive->getNameIndex($i)),'.xml'))$xml=$archive->getFromIndex($i);}finally{$archive->close();}
    }finally{@unlink($temp);}
    $dom=new \DOMDocument();if(!$xml||!@$dom->loadXML($xml,LIBXML_NONET)||$dom->documentElement->localName!=='ApplicationResponse')throw new \RuntimeException('No se encontró ApplicationResponse en el CDR.');
    $cdr=(new \Greenter\Ws\Reader\DomCdrReader(new \Greenter\Ws\Reader\XmlReader()))->getCdrResponse($xml);
    $parts=explode('-',$filename);$ref=$parts[2].'-'.$parts[3];$padded=$parts[2].'-'.str_pad($parts[3],8,'0',STR_PAD_LEFT);
    if(!$cdr||!in_array($cdr->getId(),[$ref,$padded,$filename,$parts[0].'-'.$parts[1].'-'.$padded],TRUE))return ['state'=>'uncertain','code'=>'CDR_REFERENCE','description'=>'La referencia del CDR no coincide con este documento.','cdr'=>$zip];
    return ['state'=>$cdr->getCode()==='0'?'accepted':'rejected','code'=>(string)$cdr->getCode(),'description'=>(string)$cdr->getDescription().' '.implode(' ',array_slice($cdr->getNotes()??[],0,5)),'cdr'=>$zip];
  }
}
