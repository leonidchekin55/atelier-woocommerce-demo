<?php
foreach ([['min_price'=>'','max_price'=>''],['min_price'=>'12.5','max_price'=>''],['min_price'=>'','max_price'=>'100'],['min_price'=>'0','max_price'=>'0'],['max_price'=>'  ']] as $input) {
 $_GET=$input;atelier_normalize_price_filters();
 foreach($input as $key=>$value) {
  if(trim($value)==='' && isset($_GET[$key])) throw new Exception('Empty bound retained');
  if(trim($value)!=='' && ($_GET[$key]??null)!==$value) throw new Exception('Explicit bound changed');
 }
}
echo "PASS: empty bounds removed, decimal/zero retained\n";
