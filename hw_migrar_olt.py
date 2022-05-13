#!/usr/bin/python3

# Uso
# python3 hw_migrar_olt.py -j '{"ip_origen":"172.xx.xx.aaa","ip_destino":"172.xx.xx.bbb"}'

# Busca todas las ONUs configuradas en OLT origen y queda
# iterando en un bucle infinito a la espera de que se descubran las ONU en la OLT destino.
# Metodo de comparacion por Serial Number (SN)
# Habilita los servicios correspondientes y luego elimina la ONU del Origen.
# Habilitacion por Telnet

import sys
import time
import telnetlib
import getopt
import ipaddress
import os
import time
import gc
import json
import requests
import subprocess

url_api_siga="http://172.xxx.xxx.xxx/API/api.siga.php"
url_SigaInterfaz="http://172.xx.xx.xxx/siga/"


def validateIP(ip):
	try:
		ipaddress.ip_address(ip)
	except ValueError:
		return False
	else:
		return True
		
def validateNumber(number):
	try:
		integer = int(number)
	except ValueError:
		return False
	else:
		return True

def discover_abonados_origen(tn): # Busco todo los clientes configurados en OLT
	gc.collect()
	data_return =""
	array_info=[];
	tn.write(b"display ont info summary 0 | no-more | include _ \n")
	time.sleep(.3)
	tn.write(b"\n")
	time.sleep(.5)
	return_lineid = tn.read_until('final'.encode('utf-8'),1).decode('utf-8')
	data_return = return_lineid.splitlines()
	
	for line in data_return:

		if "_OP" in line: # Contemplar Oficina Pyme
			sucursal="OPSR"
		else:
			sucursal="SR"
			
		try:

			num, sn, modelo, distancia, niveles, abonado = line.split() # Parseo la linea
			if(validateNumber(num)):
				array_info.append({
				  "sn": sn,
				  "abonado_desc": abonado.split("_")[1],
				  "abonado": abonado.split("_")[0], # Solo el numero de abonado y no el string completo
				  "sucursal": sucursal
				})
		except Exception as e:
		 pass
	return array_info

def discover_autofind(tn): # Busco los pendientes de habilitar en OLT
	gc.collect()
	data_return =""
	array_info=[];
	tn.write(b"display ont  autofind all | no-more | include Ont SN \n")
	time.sleep(.3)
	tn.write(b"\n")
	time.sleep(.5)
	return_lineid = tn.read_until('final'.encode('utf-8'),1).decode('utf-8')
	data_return = return_lineid.splitlines()

	for line in data_return:
		
		if "Ont SN" in line:
			line=line.replace("Ont SN", "").replace(":", "")
			try:
				
				sn, temp = line.split() # Parseo la linea
				array_info.append({
				  "sn_pendiente": sn
				})
			except Exception as e:
			 pass
	return array_info	
	
def return_config(tn):
	tn.write(b"return\n")
	time.sleep(.3)
	tn.write(b"config\n")
	time.sleep(.4)	


def conectar(ip,user,password):
	try:
		tn = telnetlib.Telnet(ip,23,10)
	except Exception as e:
		print ("Error Verificar IP address")
		sys.exit()

	tn.read_until(b"name:")
	tn.write(user.encode('utf-8') + b"\n")
	time.sleep(.3)
	tn.read_until(b"password:")
	tn.write(password.encode('utf-8') + b"\n")
	time.sleep(.3)

	tn.write(b"enable\n")
	time.sleep(.3)
	return tn # retorno informacion del Telnet

def getInfoSIGA(nAbonado): # Busco los servicios contratado en sistema de gestion
	retorno={}
	parametros = dict(
		accion='info',
		siga=nAbonado
	)
	datos_siga = requests.get(url=url_api_siga, params=parametros)
	siga=datos_siga.json()
	
	try:
		producto=siga['internet']
	except:
		producto=""
	
	eliminar=['INTERNET','SIMETRICO','FTTH',' ']
	for e in eliminar:
		producto=producto.replace(e, "") # elimino palabras extras

	retorno['producto']=producto
	
	try:
		retorno['CATV']=siga['CATV']
	except:
		retorno['CATV']=""
		
	retorno['siga']=nAbonado
	
	
	return retorno

def habilitar(accion,nAbonado,sucursal,cm,producto,ip,desc):

	# Busco la VLAN
	parametros = dict(
		a=11993, # accion que retorna el calculo de la vlan por numero de abonado
		n=nAbonado,
		s=sucursal,
		cm=cm,
		c="7a2bb67491e1beba74d828f730cf9cdd",
		p=producto,
		ip='172.16.16.51', 
		log=0
	)
	datos_siga = requests.get(url=url_SigaInterfaz, params=parametros)
	datos=datos_siga.json()

	# HABILITO EL SERVICIO

	datos['habilitacion']=subprocess.call(
		"python3 noc_huawei_provisioning.py -o "+ ip +" -j '{\"accion\":\""+accion+"\",\"desc\":\""+desc+"\",\"producto\":\""+producto+"\",\"mac\":\""+cm+"\",\"vlan\":\""+datos['vlan']+"\",\"nsiga\":\""+nAbonado+"\"}'"
		,shell=True
		)
	
	
	return datos

def main_json(d_json,user,password):
	os.system('clear')

	tn=conectar(d_json['ip_origen'],user,password)
	tn_destino=conectar(d_json['ip_destino'],user,password)
# ------------------------------------------------------------------------------------------------------

	return_config(tn)
	try:
		array_a_migrar=discover_abonados_origen(tn)
		
		
		while True:
			array_pendientes=discover_autofind(tn_destino)
			print(array_pendientes)
			for line_pendientes in array_pendientes:
				for line_a_migrar in array_a_migrar:
					if(line_pendientes['sn_pendiente']==line_a_migrar['sn']):
						
						siga=getInfoSIGA(line_a_migrar['abonado'])
						
						# # HABILITO INTERNET
						try:
							r=habilitar('12343',line_a_migrar['abonado'],line_a_migrar['sucursal'],line_a_migrar['sn']+";1",siga['producto'],d_json['ip_destino'],line_a_migrar['abonado_desc'])
						
							if siga['CATV']!="" and r['habilitacion']==0:
								# HABILITO CATV
								rCATV=habilitar('12343',line_a_migrar['abonado'],line_a_migrar['sucursal'],line_a_migrar['sn']+";2","RF",d_json['ip_destino'],line_a_migrar['abonado_desc'])

							print("HABILITACION en: "+d_json['ip_destino'] ,line_a_migrar['sn'],line_a_migrar['abonado'],"INTERNET:"+str(r['habilitacion']),"CATV:"+str(rCATV['habilitacion']))
							array_a_migrar.remove(line_a_migrar) # Elimino el recien habilitado para no reprocesar
							
							
							print("ELIMINAR de: "+d_json['ip_origen'])
							time.sleep(5)
							r=habilitar('56783',line_a_migrar['abonado'],line_a_migrar['sucursal'],line_a_migrar['sn']+";0",siga['producto'],d_json['ip_origen'],line_a_migrar['abonado_desc'])

						except Exception as e:
							print(e)						
				
			time.sleep(5)
	except:
		pass
		

# ---------------------------------------------- MAIN --------------------------------------------------------

def main(argv):
	if sys.version_info[0] < 3:
		raise Exception("Python 3 requerido.")

	user = 'usr'
	password = 'xxxxxxxxxxx'
	debug = False

	try:
		opts, args = getopt.getopt(argv,"j:d",["json=","debug="])
	except getopt.GetoptError:
		print('Error de argumentos.')
		sys.exit(2)
	for opt, arg in opts:

			
		if opt in ("-j", "--json"):
			data = json.loads(arg)	
			main_json(data,user,password)
			sys.exit()

if __name__ == "__main__":
	main(sys.argv[1:])