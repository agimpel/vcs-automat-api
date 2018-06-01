<?php

function log_msg($msg, $priority = 1) {
  if(LOGFILE != '' && $priority >=2) {
    file_put_contents(LOGFILE, $msg . "\n", FILE_APPEND);
  }
}

?>
