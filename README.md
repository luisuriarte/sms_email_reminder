Reemplaza los archivos de OpenEMR para poder enviar Mail y WhatsApp para las citas.
Estos se colocan en la carpeta openemr/modules/sms_email_reminder.
Antes es necesario crear en la misma carpeta otra carpeta llamada logs.
Deben ejecutar por cron cada una hora.
Que da a criterio si se envian el Email y tambien WhatsApp juntos o solo uno.
Son 2 archivos que se ejecutan mediante cron:

- php cron_email_notification_esp.php  E-Mail en Español (Fecha, hora y días).
- php cron_wsp_notification_esp.php  WhatsApp en Español (Fecha, hora y días).

En los email, se envia mensaje con logo más archivo de invitación iCalendar "ical.ics".
En WhatsApp son dos mensajes
Uno con el logo de la clínica más el mensaje. Otro con archivo adjunto iCalendar
Eje.: "ical.ics".

Para envio de WhatsApp se usan 2 empresas

 - https://ultramsg.com/ (En Octubre/2023, el valor mensual es de U$S 39.00 por instancia, envios ilimitados).
 - https://waapi.app (En febrero 2024 arranca desde U$S 6.50/mo por instancia con envios ilimitados) // No funciona desde Mayo/2025.
 - https://wasenderapi.com (En Julio 2025 arranca desde U$S 6.00/mo por instancia sin limites de mensajes)

Para que funcione bien en la tabla automatic_notification se debe
editar el campo type, en conjunto (entradas) debe quedar 'SMS','Email','WSP'.
y agregar un registro con type igual a WSP.
De la misma manera en la tabla notification_log modificar el campo type agregando WSP.

Configuración:

Los mensajes se establecen en Miscelaneos/Herramientas Comunicacion en Serie
Alli en Notificacion de SMS/WSP para Whatsapp y en Notificación para Correo Electrónico

En ambas se pueden usar las variables: "***NAME***", '***PROVIDER***', '***DATE***', '***STARTTIME***'

'***ENDTIME***', '***FACILITY_ADDRESS***', '***FACILITY_PHONE***', '***FACILITY_NAME***' , '***FACILITY_EMAIL***'

y '***USER_PREFFIX***'

'***USER PREFFIX***' Es el campo Suffix del Usuario.

'***FACILITY_NAME***', '***FACILITY_ADDRESS***', '***FACILITY_PHONE***' y '***FACILITY_EMAIL***' Son datos que se extraen

de los campos de los Centros, el telefono debe estar en formato Normal, sin el cero ni el quince Ejemplo:
1109876543. Los centros se establecen en Administración/Cínica/Centros.

Los datos de Nombre del Centro, Teléfono, EMail, APIKey, Instancias, url (Completa con https://), Servicio (UltraMSG, WaApi o WaSenderAPI),
logo Wsp y Logo email e deben colocar en los Centros, en campos no utilizados:

- Phone -> Whatsapp (Formato estandar, sin +549, sin 0 ni 15 y sin espacios ni guiones: ej. 1148654201)
- Name -> Nombre del Centro
- Steet -> Domicilio
- Website -> Url del sitio con openemr.
- Email -> Correo Electrónico
- Attn -> Logo Email
- Domain_Identifier -> Logo WhatsApp
- Facility_Npi -> Instancia
- Facility_Code -> Empresa (WaApi, UltraMSG o WaSenderAPI)
- Oid -> Clave API
