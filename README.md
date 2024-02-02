Reemplaza los archivos de OpenEMR para poder enviar Mail y WhatsApp para las citas.
Es necesario crear el directorio logs.
Estos se colocan en la carpeta openemr/modules/sms_email_reminder.
Deben ejecutar por cron cada una hora.
Son 3 archivos que se ejecutan mediante cron:
- php cron_email_notification_en.php  E-Mail en Ingles (Fecha, hora y días).
- php cron_email_notification_esp.php  E-Mail en Español (Fecha, hora y días).
- php cron_wsp_notification_esp.php  WhatsApp en Español (Fecha, hora y días).
En los email, se envia mensaje con logo más archivo de invitación iCalendar "ical.ics".
En WhatsApp son dos mensajes
Uno con el logo de la clínica más el mensaje. Otro con archivo adjunto iCalendar 
Eje.: "TURNO-fc65sc2a.ics" (8 caracteres aleatorio).
Para envio de WhatsApp se usa la empresa https://ultramsg.com/ (En Octubre/2023, el 
valor mensual es de U$S 39, envios ilimitados).
Para que funcione bien en la tabla automatic_notification se debe
editar el campo type, en conjunto (entradas) debe quedar 'SMS','Email','WSP'.
y agregar un registro con type igual a WSP.
De la misma manera en la tabla notification_log modificar el campo type.
Tambien es necesario hacerlo en la tabla notification-settings.
