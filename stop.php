<?php

$output = '';
exec('./stop.sh',$output);
var_dump($output);
echo "<br/>";
echo "1";
