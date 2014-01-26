<?php
/**
 * @file Tests the WebAPI and displays the results.
 */
use Siel\Acumulus\Test\Test;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once 'Siel/Acumulus/TranslatorInterface.php';
  require_once 'Siel/Acumulus/BaseTranslator.php';
  require_once 'Siel/Acumulus/ConfigInterface.php';
  require_once 'Siel/Acumulus/BaseConfig.php';
  require_once 'Siel/Acumulus/WebAPICommunication.php';
  require_once 'Siel/Acumulus/WebAPI.php';
  require_once 'Siel/Acumulus/Test/TestConfig.php';
  require_once 'Siel/Acumulus/Test/Test.php';
  $test = new Test();
  $results = $test->run();
  $submit = 'Rerun tests';
  $rows = min(40, substr_count($results, "\n"));
}
else {
  $results = '';
  $submit = 'Run tests';
  $rows = 2;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title>Test Acumulus Web API</title>
  <style type="text/css">
    body {
      background: #336699;
      font-family: "Lucida Grande", Tahoma, Arial, Verdana, sans-serif;
      margin: 10px 0 0 20px;
    }
    form {
      margin-left: 20px;
    }
    textarea {
      display: block;
      width: 80%;
    }
  </style>
</head>
<body>
<h1>Test Acumulus Web API</h1>
<form method="post" action="">
  <label class="description" for="element_1">Test results:</label>
  <textarea id="element_1" name="element_1" rows="<?= $rows ?>"><?= $results ?></textarea>
  <input id="submit" class="button_text" type="submit" name="submit" value="<?= $submit ?>"/>
</form>
</body>
</html>
