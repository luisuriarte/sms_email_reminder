<?php
require_once '../../vendor/autoload.php'; // Incluir GuzzleHTTP
require_once(__DIR__ . "/../../interface/globals.php");
require_once(__DIR__ . "/../../library/appointments.inc.php");
require_once(__DIR__ . "/../../library/patient_tracker.inc.php");

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Configuración de WaSenderAPI
$apiKey = 'd492646b0f496fe95981ba074da1e212d1238a58c74fad785971fe2cc39ab80a';
$baseUrl = 'https://www.wasenderapi.com/api';

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
        // Consultar mensajes en la base de datos
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
                <h2 class="mb-4 text-center">Leer Mensajes Enviados - WaSenderAPI</h2>
                <p class="text-muted text-center">Ingresa el número de teléfono (10 dígitos, ej. 3404540440) para ver los mensajes enviados.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="mb-4">
                    <div class="mb-3">
                        <label for="phoneNumber" class="form-label">Número de teléfono (sin +549 y sin 15):</label>
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

    <!-- Bootstrap 5 JS (para componentes como tooltips o modales, si se usan en el futuro) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>