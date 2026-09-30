<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/naira.php';
require_once __DIR__.'/providers/ProviderRegistry.php';

function customerUnitPrice(string $providerSellingPrice,string $localMarkup='0.00'):string
{
    return nairaAdd($providerSellingPrice,nairaDecimal($localMarkup));
}

function createCustomerOrder(int $userId,array $product,int $quantity,string $localMarkup='0.00'):array
{
    $quantity=max(1,$quantity);
    $min=max(1,(int)($product['min_quantity']??1));$max=min(100,(int)($product['max_quantity']??100));
    if($quantity<$min||$quantity>$max)throw new InvalidArgumentException('Quantity is outside the permitted range.');
    $provider='logspanel';$unit=customerUnitPrice(nairaDecimal((string)($product['selling_price']??$product['reseller_price']??'0')),$localMarkup);
    $total=nairaMultiply($unit,$quantity);$idempotency=bin2hex(random_bytes(16));$reference='order-'.$idempotency;$pdo=db();

    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');$lock->execute([$userId]);$balance=$lock->fetchColumn();
        if($balance===false)throw new RuntimeException('Customer account was not found.');
        if(nairaKobo((string)$balance)<nairaKobo($total))throw new RuntimeException('Insufficient wallet balance.');
        $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description) VALUES(?,?,?,?,?,?,?)')
            ->execute([$userId,'debit',nairaKobo($total),$reference,$provider,null,'Purchase: '.(string)$product['name']]);
        $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?')->execute([$total,$userId]);
        $pdo->prepare('INSERT INTO orders(user_id,product_id,product_name,qty,unit_price,total_amount,provider,status,idempotency_key,product_details) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId,(int)($product['id']??0),(string)$product['name'],$quantity,$unit,$total,$provider,'pending',$idempotency,'']);
        $orderId=(int)$pdo->lastInsertId();$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

    $result=ProviderRegistry::get($provider)->createOrder([
        'product_ref'=>(string)($product['product_ref']??''),'category_id'=>(int)($product['id']??0),'quantity'=>$quantity
    ],$idempotency);

    if(!$result['ok']){
        $pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND status=?')->execute(['failed',$orderId,'pending']);
            $refundRef='refund-'.$idempotency;
            $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description) VALUES(?,?,?,?,?,?,?)')
                ->execute([$userId,'refund',nairaKobo($total),$refundRef,$provider,$refundRef,'Refund for failed provider order #'.$orderId]);
            $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([$total,$userId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        throw new RuntimeException($result['error']??'Provider order failed.');
    }

    $data=is_array($result['data']['data']??null)?$result['data']['data']:[];
    $providerRef=(string)($data['order_id']??'');
    $status=strtolower((string)($data['status']??($result['status']===201?'completed':'pending')));
    $details=isset($data['items'])&&is_array($data['items'])?json_encode($data['items'],JSON_UNESCAPED_SLASHES):'';
    db()->prepare('UPDATE orders SET provider_ref=?,api_order_id=?,status=?,product_details=? WHERE id=?')
        ->execute([$providerRef!==''?$providerRef:null,$providerRef!==''?$providerRef:null,$status==='completed'?'completed':'pending',$details,$orderId]);
    return ['id'=>$orderId,'status'=>$status,'provider_ref'=>$providerRef,'total'=>$total];
}
