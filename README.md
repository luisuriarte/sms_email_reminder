<body lang="es-AR" link="#000080" vlink="#800000" dir="ltr"><p style="line-height: 100%; margin-bottom: 0cm">
<font color="#cccccc"><span style="background: #1f1f1f">Reemplaza los
archivos de OpenEMR para poder enviar Mail y WhatsApp para las citas.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Es
necesario crear el directorio logs.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Estos
se colocan en la carpeta openemr/modules/sms_email_reminder.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Deben
ejecutar por cron cada una hora.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Que
da a criterio si se envian el Email y tambien WhatsApp juntos o solo
uno.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Son
2 archivos que se ejecutan mediante cron:</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#6796e6">-</font><font color="#cccccc">
php cron_email_notification_esp.php &nbsp;E-Mail en Español (Fecha,
hora y días).</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#6796e6">-</font><font color="#cccccc">
php cron_wsp_notification_esp.php &nbsp;WhatsApp en Español (Fecha,
hora y días).</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">En
los email, se envia mensaje con logo más archivo de invitación
iCalendar &quot;ical.ics&quot;.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">En
WhatsApp son dos mensajes</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Uno
con el logo de la clínica más el mensaje. Otro con archivo adjunto
iCalendar </span></font>
</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Eje.:
&quot;ical.ics&quot;.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Para
envio de WhatsApp se usan 2 empresas </span></font>
</p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">&nbsp;</font><font color="#6796e6">-</font><font color="#cccccc">
https://ultramsg.com/ (En Octubre/2023, el valor mensual es de U$S 39
por instancia, envios ilimitados).</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">&nbsp;</font><font color="#6796e6">-</font><font color="#cccccc">
https://waapi.app (En febrero 2024 arranca desde U$S 6.5 por
instancia con envios ilimitados).</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Para
que funcione bien en la tabla automatic_notification se debe</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">editar
el campo type, en conjunto (entradas) debe quedar
'SMS','Email','WSP'.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">y
agregar un registro con type igual a WSP.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">De
la misma manera en la tabla notification_log modificar el campo type
agreganto WSP.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Configuración:</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Los
mensajes se establecen en Miscelaneos/Herramientas Comunicacion en
Serie</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Alli
en Notificacion de SMS/WSP para Whatsapp y en Notificación para
Correo Electrónico</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">En
ambas se pueden usar las variables: &quot;</font>***NAME***<font color="#cccccc">&quot;,
&quot;</font>***PROVIDER***<font color="#cccccc">&quot;,
&quot;</font>***DATE***<font color="#cccccc">&quot;,
&quot;</font>***STARTTIME***<font color="#cccccc">&quot;</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">&quot;</font>***ENDTIME***<font color="#cccccc">&quot;,
&quot;</font>***FACILITY_ADDRESS***<font color="#cccccc">&quot;,
&quot;</font>***FACILITY_PHONE***<font color="#cccccc">&quot;,
&quot;</font>***FACILITY_NAME***<font color="#cccccc">&quot; ,
&quot;</font>***FACILITY_EMAIL***<font color="#cccccc">&quot;</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">y
&quot;</font>***USER_PREFFIX***<font color="#cccccc">&quot;</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">&quot;</font>***USER
PREFFIX***<font color="#cccccc">&quot; Es el campo Suffix del
Usuario.</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#cccccc">&quot;</font>***FACILITY_NAME***<font color="#cccccc">&quot;,
&quot;</font>***FACILITY_ADDRESS***<font color="#cccccc">&quot;,
&quot;</font>***FACILITY_PHONE***<font color="#cccccc">&quot; y
&quot;</font>***FACILITY_EMAIL***<font color="#cccccc">&quot; Son
datos que se extraen</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">de
los campos de los Centros, el telefono debe estar en formato Normal,
sin el cero ni el quince Ejemplo:</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><span style="background: #1f1f1f"><font color="#6796e6">1109876543.</font><font color="#cccccc">
Los centros se establecen en Administración/Cínica/Centros.</font></span></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Los
datos de Nombre del Centro, Teléfono, EMail, APIKey, Instancias, url
(Completa con https://), Servicio (UltraMSG o WaApi), </span></font>
</p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">logo
Wsp y Logo email e deben colocar en los Centros, en campos no
utilizados:</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Phone
-&gt; Whatsapp</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Name
-&gt; Nombre del Centro</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Steet
-&gt; Domicilio</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Website
-&gt; Url completa.</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Email
-&gt; Correo Electrónico</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Attn
-&gt; Logo Email</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Domain_Identifier
-&gt; Logo WhatsApp</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Facility_Npi
-&gt; Instancia</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Facility_Code
-&gt; Empresa (WaApi o UltraMSG)</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><font color="#cccccc"><span style="background: #1f1f1f">Oid
-&gt; Clave API</span></font></p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
<p style="line-height: 100%; margin-bottom: 0cm"><br/>

</p>
</body>

