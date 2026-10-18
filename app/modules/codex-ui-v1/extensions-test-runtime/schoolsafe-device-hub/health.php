<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
exit(is_file('/tmp/device-collector-heartbeat') && time()-filemtime('/tmp/device-collector-heartbeat')<180?0:1);
