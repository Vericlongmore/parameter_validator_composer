<?php
/**
 * Created by PhpStorm.
 * User: longmore
 * Date: 17/12/14
 * Time: 下午3:41
 */


use Validator\Validator;

require 'vendor/autoload.php';


$receive_data = [];
$data['bank'] = $receive_data['bank'] ? intval($receive_data['bank']) : NULL;
$data['card_number'] = $receive_data['card_number'] ? $receive_data['card_number'] : NULL;
$data['province'] = $receive_data['province'] ? strval($receive_data['province']) : NULL;
$data['city'] = $receive_data['city'] ? strval($receive_data['city']) : NULL;
$data['branch_bank_name'] = $receive_data['branch_bank_name'] ? strval($receive_data['branch_bank_name']) : NULL;
    
try {
    (new Validator())->execute((array)$data, [
        'bank' => ['type' => 'integer', 'enum_eq' => array_keys([1=>'',2=>''])],
        'card_number' => ['type' => 'string'],
        'province' => ['required' => true],
        'city' => ['required' => true],
        'branch_bank_name' => ['required' => true],
    ]);

} catch (\Exception $e) {
    var_dump($e->getMessage());
}

