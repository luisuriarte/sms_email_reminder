Reemplaza los archivos de OpenEMR para poder enviar Mail y WhatsApp para las citas.
Es necesario crear el directorio logs.
Estos se colocan en la carpeta openemr/modules/sms_email_reminder.
Deben ejecutar por cron cada una hora.
Son 4 archivos que se ejecutan mediante cron:
- php cron_email_notification_en.php  E-Mail en Ingles (Fecha, hora y días).
- php cron_email_notification_esp.php  E-Mail en Español (Fecha, hora y días).
- php cron_ultra_notification_esp.php  WhatsApp en Español (Fecha, hora y días).
- php cron_wappi_notification_esp.php  WhatsApp en Español (Fecha, hora y días).
En los email, se envia mensaje con logo más archivo de invitación iCalendar "ical.ics".
En WhatsApp son dos mensajes
Uno con el logo de la clínica más el mensaje. Otro con archivo adjunto iCalendar 
Eje.: "ical.ics".
Para envio de WhatsApp se usan 2 empresas 
 - https://ultramsg.com/ (En Octubre/2023, el valor mensual es de U$S 39 por instancia, envios ilimitados).
 - https://waapi.app (En febrero 2024 arranca desde U$S 6.5 por instancia con envios ilimitados).

Para que funcione bien en la tabla automatic_notification se debe
editar el campo type, en conjunto (entradas) debe quedar 'SMS','Email','WSP'.
y agregar un registro con type igual a WSP.
De la misma manera en la tabla notification_log modificar el campo type.
Tambien es necesario hacerlo en la tabla notification-settings.
Se debe agregar una linea en la tabla automatic_notification que contenga en el campo type 'WSP'.

Configuración:

Los mensajes se establecen en Miscelaneos/Herramientas Comunicacion en Serie
Alli en Notificacion de SMS/WSP para Whatsapp y en Notificación para Correo Electrónico
En ambas se pueden usar las variables: ***NAME***, ***PROVIDER***, ***DATE***, ***STARTTIME***
***ENDTIME***, ***FACILITY_ADDRESS***, ***FACILITY_PHONE***, ***FACILITY_NAME*** y ***FACILITY_EMAIL***

***FACILITY_NAME***, ***FACILITY_ADDRESS***, ***FACILITY_PHONE*** y ***FACILITY_EMAIL*** Son datos que se extraen
de los campos de los Centros, el telefono debe estar en formato Normal, sin el cero ni el quince Ejemplo:
1109876543. Los centros se establecen en Administración/Cínica/Centros.
En Admin/config/Marca(Branding) se bdebe agregar una URL real en Vínculo de soporte online.
Los datos de WSP, EMail, APIKey e Instancias estan en Administracion/Configuración/Notificaciones
Tambien se debe rellenar AiKey e Instacias en Miscelaneos / Herramienta de Comunicación para series /
SMS/CORREO ELECTRONICO Ajustes Alerta. En estos casos el Nombre de Usuario es la Instancia y Agragar Clave Api
La contraseña de usuario no pimporta.

