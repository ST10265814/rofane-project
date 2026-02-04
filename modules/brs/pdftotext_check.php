<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

function find_pdftotext() {
    if (defined('PDFTOTEXT_PATH') && PDFTOTEXT_PATH && file_exists(PDFTOTEXT_PATH)) return PDFTOTEXT_PATH;
    $env = getenv('PDFTOTEXT_PATH');
    if ($env && file_exists($env)) return $env;

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $out = @shell_exec('where pdftotext');
        if ($out) {
            $lines = preg_split('/\r?\n/', trim($out));
            if (!empty($lines[0]) && file_exists($lines[0])) return $lines[0];
        }
        $candidates = [
            'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
            'C:\\Program Files\\poppler-24.02.0\\Library\\bin\\pdftotext.exe',
            'C:\\Program Files\\poppler-23.11.0\\Library\\bin\\pdftotext.exe',
            __DIR__ . '\\\\..\\..\\..\\bin\\pdftotext.exe'
        ];
        foreach ($candidates as $p) if (file_exists($p)) return $p;
    } else {
        $out = @shell_exec('which pdftotext');
        if ($out) {
            $path = trim($out);
            if ($path && file_exists($path)) return $path;
        }
        foreach (['/usr/bin/pdftotext','/usr/local/bin/pdftotext','/opt/homebrew/bin/pdftotext'] as $p)
            if (file_exists($p)) return $p;
    }
    return null;
}

function extract_pdf_text($pdfPath) {
    $exe = find_pdftotext();
    if ($exe) {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $cmd = '"' . $exe . '" -layout "' . $pdfPath . '" -';
        } else {
            $cmd = escapeshellarg($exe) . ' -layout ' . escapeshellarg($pdfPath) . ' -';
        }
        $txt = @shell_exec($cmd . ' 2>&1');
        if ($txt && trim($txt) !== '') return $txt;
        return $txt; // show raw output for debugging
    }
    return '';
}

$disable = ini_get('disable_functions');
$shell_ok = function_exists('shell_exec');
$exe = find_pdftotext();

$test_output = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['pdf']) && $_FILES['pdf']['error']===UPLOAD_ERR_OK) {
    $path = $_FILES['pdf']['tmp_name'];
    $test_output = extract_pdf_text($path);
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>PDF Text Tool Check</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h3 class="mb-3">PDF Text Tool Check</h3>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div><strong>OS:</strong> <?= PHP_OS_FAMILY ?>, PHP <?= PHP_VERSION ?></div>
      <div><strong>shell_exec available:</strong> <?= $shell_ok ? 'Yes' : 'No' ?></div>
      <div><strong>Disabled functions:</strong> <?= htmlspecialchars($disable ?: '(none)') ?></div>
      <div><strong>Detected pdftotext:</strong> <?= $exe ? htmlspecialchars($exe) : 'Not found' ?></div>
      <div class="mt-2">
        <code>define('PDFTOTEXT_PATH', 'C:\\Program Files\\poppler\\bin\\pdftotext.exe');</code> in <code>includes/config.php</code>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h6>Quick Test</h6>
      <form method="POST" enctype="multipart/form-data" class="row g-2">
        <div class="col-12">
          <input type="file" name="pdf" class="form-control" accept="application/pdf" required>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit">Extract</button>
        </div>
      </form>
      <?php if ($_SERVER['REQUEST_METHOD']==='POST'): ?>
        <hr/>
        <pre style="white-space: pre-wrap;"><?= htmlspecialchars($test_output ?: 'No text extracted (see tool detection above).') ?></pre>
      <?php endif; ?>
    </div>
  </div>

  <a href="generate_cases.php?brs_id=1" class="btn btn-outline-secondary">Back to Generate Cases</a>
</div>
</body>
</html>
