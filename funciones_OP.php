<?php
$headers =[
    'Accept' => 'application/json',
    'Authorization' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
  ];
//*************************************************
// Aplicar Cambios??
$apply_mkt=true;
//*************************************************


// mikrotik default
$server_configuration_id="f90b53f9-1f60-46c3-b792-xxxxxxxxxxxxxxx";

///**************** DATOS TEST *******************
/*$accion="88882";
$numeroSIGA="157684"; //134457   157684     - 158903 AIRE
$producto="Eco Turbo Nuevo";
$cmac="4C:5E:0C:38:71:4A";
$apellido="SIGA Prueba";
accion_OP();
*/
///**************** FIN DATOS TEST *******************

function accion_OP() {
	global $accion,$cmac,$numeroSIGA,$apellido,$producto,$sucursal,$server_configuration_id;
	
	$cmac = implode(":", str_split(str_replace(array("-",":",".","_"), "", $cmac), 2)); //FORMATO DE MAC 4C:5E:0C:38:71:4A
	$abonadoApellido=strtoupper (getSubString($apellido,25)); //25 CARACTERES
	
	
	

		
	switch ($accion) {
		
		case '12342': // HABILITAR / CREAR ABONADO
			
			$dataUsuario=cliente($numeroSIGA)['data'][0];
			
			if($dataUsuario['id']==""){ //VALIDO QUE EL CLIENTE NO EXISTE
				//echo "Crear usuario";
				$nuevo_cliente=new_cliente($abonadoApellido,$numeroSIGA)['data'];
				$dataUsuario['id']=$nuevo_cliente['id'];
			}
			
			$dataPlan=get_plan($producto);
			$nuevo_contrato=new_contratos($dataUsuario['id'],$dataPlan['id'],$server_configuration_id,$cmac)['data'];
			
			if($nuevo_contrato['id']!=""){ 
				echo json_encode(array('accion'=>'HABILITAR AIRE OP','salida'=>1,'mac'=>$cmac,'texto'=>'HABILITADO'));
				log_basededatos($numeroSIGA,$cmac,'AEREO','HABILITAR AIRE',$sucursal,$producto,'','');
			}else{
				echo json_encode(array('accion'=>'HABILITAR AIRE OP','salida'=>0,'mac'=>$cmac,'texto'=>'ERROR: MAC debe ser unica.'));

			}
	
			//var_dump($dataUsuario );
			//var_dump($dataPlan );
		break;
		
		case '56782': // ELIMINAR CONTRATOS

			$dataUsuario=cliente($numeroSIGA)['data'][0];
				if($dataUsuario['id']==""){  //VALIDO QUE EL CLIENTE EXISTE
					$retorno['cliente']="Cliente NO encontrado";
					echo json_encode($retorno); 
					break;
				}
			$retorno_editar_eliminar_contratos=editar_eliminar_contratos($dataUsuario['id']);
			
			if($retorno_editar_eliminar_contratos['status']==200){ 
				aplicar_cambio_mikrotiks($retorno_editar_eliminar_contratos["data"]["server_configuration_id"]);
				echo json_encode(array('accion'=>'ELIMINAR AIRE OP','salida'=>1,'mac'=>$cmac,'texto'=>'ELIMINADO'));
				log_basededatos($numeroSIGA,$cmac,'AEREO','ELIMINAR AIRE',$sucursal,$producto,'','');

			}else{
				echo json_encode(array('accion'=>'ELIMINAR AIRE OP','salida'=>0,'mac'=>$cmac,'texto'=>'ERROR: No existe contrato.'));
			}
			
		break;
		case '77772': // SUSPENDER CONTRATOS

			$dataUsuario=cliente($numeroSIGA)['data'][0];
				if($dataUsuario['id']==""){  //VALIDO QUE EL CLIENTE EXISTE
					$retorno['cliente']="Cliente NO encontrado";
					echo json_encode($retorno); 
					break;
				}			
			$retorno_editar_eliminar_contratos=editar_eliminar_contratos($dataUsuario['id']);
			if($retorno_editar_eliminar_contratos['status']==200){ 
				aplicar_cambio_mikrotiks($retorno_editar_eliminar_contratos["data"]["server_configuration_id"]);
				echo json_encode(array('accion'=>'SUSPENDER AIRE OP','salida'=>1,'mac'=>$cmac,'texto'=>'SUSPENDIDO'));
				log_basededatos($numeroSIGA,$cmac,'AEREO','SUSPENDER AIRE',$sucursal,$producto,'','');
			}else{
				echo json_encode(array('accion'=>'SUSPENDER AIRE OP','salida'=>0,'mac'=>$cmac,'texto'=>'ERROR: No existe contrato.'));
			}
			
		break;
		case '88882': // RESTABLECER CONTRATOS

			$dataUsuario=cliente($numeroSIGA)['data'][0];
				if($dataUsuario['id']==""){  //VALIDO QUE EL CLIENTE EXISTE
					$retorno['cliente']="Cliente NO encontrado";
					echo json_encode($retorno); 
					break;
				}			
			$retorno_editar_eliminar_contratos=editar_eliminar_contratos($dataUsuario['id']);
			if($retorno_editar_eliminar_contratos['status']==200){ 
				aplicar_cambio_mikrotiks($retorno_editar_eliminar_contratos["data"]["server_configuration_id"]);
				echo json_encode(array('accion'=>'RESTABLECER AIRE OP','salida'=>1,'mac'=>$cmac,'texto'=>'RESTABLECIDO'));
				log_basededatos($numeroSIGA,$cmac,'AEREO','RESTABLECER AIRE',$sucursal,$producto,'','');
			}else{
				echo json_encode(array('accion'=>'RESTABLECER AIRE OP','salida'=>0,'mac'=>$cmac,'texto'=>'ERROR: No existe contrato.'));
			}
		
		break;
		case '55552': // CAMBIO DE PRODUCTO

			$dataUsuario=cliente($numeroSIGA)['data'][0];
				if($dataUsuario['id']==""){  //VALIDO QUE EL CLIENTE EXISTE
					$retorno['cliente']="Cliente NO encontrado";
					echo json_encode($retorno); 
					break;
				}
			$retorno_editar_eliminar_contratos=editar_eliminar_contratos($dataUsuario['id']);
			if($retorno_editar_eliminar_contratos['status']==200){ 
				aplicar_cambio_mikrotiks($retorno_editar_eliminar_contratos["data"]["server_configuration_id"]);			
				echo json_encode(array('accion'=>'CAMBIO DE PRODUCTO AIRE OP','salida'=>1,'mac'=>$cmac,'texto'=>$producto));
				log_basededatos($numeroSIGA,$cmac,'AEREO','CAMBIO DE PRODUCTO AIRE',$sucursal,$producto,'','');				
			}else{
				echo json_encode(array('accion'=>'CAMBIO DE PRODUCTO AIRE OP','salida'=>0,'mac'=>$cmac,'texto'=>'ERROR: No existe contrato.'));
			}
		
		break;		
	
	}
}



// ********* FUNCIONES PROPIAS ******

function editar_eliminar_contratos($id_cliente){
	global $accion,$producto;
	$contratos=get_contrato($id_cliente)['data'];

	foreach ($contratos as $clave => $valor)
			{
			switch ($accion) {
				case '56782': // ELIMINAR
					return eliminar_contrato($valor['id']);
				break;
				case '77772': // SUSPENDER
					return editar_contrato($valor['id'],'state=disabled');
				break;
				case '88882': // RESTABLECER
					return editar_contrato($valor['id'],'state=enabled');
				break;				
				case '55552': // CAMBIO DE PRODUCTO
					$plan_id=get_plan($producto)['id'];
					 return editar_contrato($valor['id'],"plan_id=$plan_id");
				break;	
			}
		}
}

function get_plan($producto){
	$planes=get_plans($producto)['data'];
	foreach ($planes as $clave => $valor)
	{
	$valor['name']=strtoupper ($valor['name']);
	$producto=strtoupper ($producto);
	if(cleanString($valor['name'])==cleanString($producto)){	
		$dataPlan=$planes[$clave];
		return $dataPlan;
		}
	}
}

// ********* FUNCIONES WISPRO curl ******

// *************************** clients ****************

function cliente($nsiga){
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/clients?custom_id_eq=$nsiga";
	return json_decode(exec("curl --request GET --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
			
}

function editar_cliente($id_cliente,$parametros){ //name=SIGA-  // blanquer un dato -->  &address_additional_data= 
	global $headers;
	$parametros=cleanString($parametros);
	$parametros = str_replace(" ", "%20", $parametros);
	
	$url="https://www.cloud.wispro.co/api/v1/clients/$id_cliente?$parametros";
	return json_decode(exec("curl --request PUT --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}

function new_cliente($nombre,$nsiga){
	global $headers;
	$nombre=cleanString($nombre);
	$nombre = str_replace(" ", "%20", $nombre);
	
	$url="https://www.cloud.wispro.co/api/v1/clients?name=$nombre&custom_id=$nsiga";
	return json_decode(exec("curl --request POST --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}

function eliminar_cliente($id_cliente){  
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/clients/$id_cliente";
	return json_decode(exec("curl --request DELETE --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}


// *************************** contracts ****************

function new_contratos($client_id,$plan_id,$server_configuration_id,$mac_address){
	global $headers;
	$ip="0.0.".rand(2, 254).".".rand(2, 254); //GENERO IP RANDOM
	$latitude="null";
	$longitude="null";
	
	$url="https://www.cloud.wispro.co/api/v1/contracts?client_id=$client_id&plan_id=$plan_id&server_configuration_id=$server_configuration_id&ip=$ip&latitude=$latitude&longitude=$longitude&dhcp_enabled=true&mac_address=$mac_address&state=disabled";
	return json_decode(exec("curl --request POST --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}

function get_contrato($id_cliente){
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/contracts?client_id_eq=$id_cliente";
	return json_decode(exec("curl --request GET --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
			
}

function editar_contrato($id_contrato,$parametros){ //state=enabled&address_additional_data=info adi  // blanquer un dato -->  &address_additional_data= 
	global $headers;
	$parametros=cleanString($parametros);
	$parametros = str_replace(" ", "%20", $parametros);
	
	$url="https://www.cloud.wispro.co/api/v1/contracts/$id_contrato?$parametros";
	return json_decode(exec("curl --request PUT --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
			
}

function eliminar_contrato($id_contrato){  
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/contracts/$id_contrato";
	return json_decode(exec("curl --request DELETE --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
			
}


// *************************** plans ****************


function get_plans(){
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/plans?per_page=100";
	return json_decode(exec("curl --request GET --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
			
}



// *************************** mikrotiks ****************

function get_mikrotiks(){
	global $headers;
	$url="https://www.cloud.wispro.co/api/v1/mikrotiks";
	return json_decode(exec("curl --request DELETE --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}


function aplicar_cambio_mikrotiks($server_configuration_id){
	global $headers,$apply_mkt;
	if($apply_mkt==false){return 0;}
	$url="https://www.cloud.wispro.co/api/v1/mikrotiks/$server_configuration_id/apply_changes";
	return json_decode(exec("curl --request PUT --url '$url' --header 'Accept: $headers[Accept]' --header 'Authorization: $headers[Authorization]'"), true);
}

// ********* FUNCIONES EXTRAS NO PROPIAS ******

 
function cleanString($text) {
    $utf8 = array(
        '/[áàâãªä]/u'   =>   'a',
        '/[ÁÀÂÃÄ]/u'    =>   'A',
        '/[ÍÌÎÏ]/u'     =>   'I',
        '/[íìîï]/u'     =>   'i',
        '/[éèêë]/u'     =>   'e',
        '/[ÉÈÊË]/u'     =>   'E',
        '/[óòôõºö]/u'   =>   'o',
        '/[ÓÒÔÕÖ]/u'    =>   'O',
        '/[úùûü]/u'     =>   'u',
        '/[ÚÙÛÜ]/u'     =>   'U',
        '/ç/'           =>   'c',
        '/Ç/'           =>   'C',
        '/ñ/'           =>   'n',
        '/Ñ/'           =>   'N',
        '/–/'           =>   '-', // UTF-8 hyphen to "normal" hyphen
        '/[’‘‹›‚]/u'    =>   ' ', // Literally a single quote
        '/[“”«»„]/u'    =>   ' ', // Double quote
        '/ /'           =>   ' ', // nonbreaking space (equiv. to 0x160)
    );
    return preg_replace(array_keys($utf8), array_values($utf8), $text);
}




?>