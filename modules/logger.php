<?php

function log_msg($msg, $priority = 2) {
  if(LOGFILE != '' && $priority >=2) {
    file_put_contents(LOGFILE, $msg . "\n", FILE_APPEND);
  }
}

?>
