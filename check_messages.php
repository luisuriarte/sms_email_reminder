<?php
require_once '../../vendor/autoload.php'; // Incluir GuzzleHTTP
require_once __DIR__ . '/../../interface/globals.php';
require_once __DIR__ . '/../../library/appointments.inc.php';
require_once __DIR__ . '/../../library/patient_tracker.inc.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Inicializar variables para el formulario
$phoneNumber = '';
$result = '';
$errors = [];

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');

    // Validar entrada
    if (empty($phoneNumber)) {
        $errors[] = 'El número de teléfono es obligatorio.';
    } elseif (!preg_match('/^\d{10}$/', $phoneNumber)) {
        $errors[] = 'El número de teléfono debe tener 10 dígitos (ej. 3404540440).';
    }

    if (empty($errors)) {
        // Obtener API Key desde la tabla facility basado en el número de teléfono
        try {
            $sql = "SELECT f.oid 
                    FROM patient_data pd 
                    JOIN openemr_postcalendar_events ope ON pd.pid = ope.pc_pid 
                    JOIN facility f ON ope.pc_facility = f.id 
                    WHERE pd.phone_cell = ? AND ope.pc_apptstatus = 'WSP' 
                    ORDER BY ope.pc_eventDate DESC LIMIT 1";
            $facility = sqlQuery($sql, [$phoneNumber]);
            $apiKey = $facility['oid'] ?? '';
            if (empty($apiKey)) {
                $errors[] = 'No se encontró la clave API para el número de teléfono proporcionado.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al consultar la tabla facility: ' . $e->getMessage();
        }

        // Consultar mensajes en la base de datos
        if (empty($errors)) {
            $sql = "SELECT iLogId, msg_id, message, patient_info, dSentDateTime, status 
                    FROM notification_log 
                    WHERE type = 'WSP' AND patient_info LIKE ? 
                    ORDER BY dSentDateTime DESC";
            $resultSet = sqlStatement($sql, ["%|||$phoneNumber|||%"]);
            $messages = [];
            while ($row = sqlFetchArray($resultSet)) {
                $messages[] = $row;
            }

            if (!empty($messages)) {
                $result = "<h3 class='mb-4'>Mensajes enviados a +549$phoneNumber</h3>";
                $result .= "<div class='table-responsive'>";
                $result .= "<table class='table table-striped table-bordered table-hover'>";
                $result .= "<thead class='table-light'><tr><th>ID del Log</th><th>ID del Mensaje</th><th>Mensaje</th><th>Estado</th><th>Fecha de Envío</th><th>Acción</th></tr></thead>";
                $result .= "<tbody>";
                foreach ($messages as $message) {
                    $result .= "<tr>";
                    $result .= "<td>" . htmlspecialchars($message['iLogId']) . "</td>";
                    $result .= "<td>" . htmlspecialchars($message['msg_id'] ?? 'No disponible') . "</td>";
                    $result .= "<td>" . htmlspecialchars($message['message']) . "</td>";
                    $result .= "<td>" . htmlspecialchars($message['status'] ?? 'No disponible') . "</td>";
                    $result .= "<td>" . htmlspecialchars($message['dSentDateTime']) . "</td>";
                    $result .= "<td>";
                    if (!empty($message['msg_id'])) {
                        $result .= "<form method='post' action='check_status.php' class='d-inline'>";
                        $result .= "<input type='hidden' name='msgId' value='" . htmlspecialchars($message['msg_id']) . "'>";
                        $result .= "<input type='hidden' name='phoneNumber' value='" . htmlspecialchars($phoneNumber) . "'>";
                        $result .= "<button type='submit' class='btn btn-sm btn-primary'>Ver Estado</button>";
                        $result .= "</form>";
                    }
                    $result .= "</td>";
                    $result .= "</tr>";
                }
                $result .= "</tbody></table></div>";
            } else {
                $result = "<div class='alert alert-info'>No se encontraron mensajes para +549$phoneNumber.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leer Mensajes Enviados - WaSenderAPI</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4 text-center">Leer Mensajes Enviados</h2>
                <p class="text-muted text-center">Ingresa el número de teléfono (sin +549, sin 15 y sin espacios ni guiones):</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="mb-4">
                    <div class="mb-3">
                        <input type="text" name="phoneNumber" id="phoneNumber" class="form-control" value="<?php echo htmlspecialchars($phoneNumber); ?>" placeholder="3404540440" pattern="\d{10}" required>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Consultar Mensajes</button>
                    </div>
                </form>

                <?php if ($result): ?>
                    <div class="result">
                        <?php echo $result; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>