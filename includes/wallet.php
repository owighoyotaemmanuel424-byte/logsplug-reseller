<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/naira.php';

function walletDebit(PDO $pdo,int $userId,string $amount,string $reference,string $description,string $provider,?string $providerRef=null): void
{
    $kobo=nairaKobo($amount);
    if($kobo<=0) throw new InvalidArgumentException('Debit must be positive.');
    $pdo->beginTransaction();
    try {
        $existing=$pdo->prepare('SELECT id FROM wallet_transactions WHERE provider=? AND provider_ref IS NOT DISTINCT FROM ? LIMIT 1 FOR UPDATE');
        $existing->execute([$provider,$providerRef]);
        if($existing->fetchColumn()){ $pdo->commit(); return; }

        $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description)
            VALUES(?,?,?,?,?,?,?)')->execute([$userId,'debit',$kobo,$reference,$provider,$providerRef,$description]);
        $st=$pdo->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=? AND wallet_balance>=?');
        $st->execute([nairaDecimal($amount),$userId,nairaDecimal($amount)]);
        if($st->rowCount()!==1) throw new RuntimeException('Insufficient wallet balance.');
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function walletCredit(PDO $pdo,int $userId,string $amount,string $reference,string $description,string $provider='internal',?string $providerRef=null): void
{
    $kobo=nairaKobo($amount);
    $pdo->beginTransaction();
    try {
        if($providerRef!==null){
            $existing=$pdo->prepare('SELECT id FROM wallet_transactions WHERE provider=? AND provider_ref=? LIMIT 1 FOR UPDATE');
            $existing->execute([$provider,$providerRef]);
            if($existing->fetchColumn()){ $pdo->commit(); return; }
        }
        $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description)
            VALUES(?,?,?,?,?,?,?)')->execute([$userId,'credit',$kobo,$reference,$provider,$providerRef,$description]);
        $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([nairaDecimal($amount),$userId]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function walletRefund(PDO $pdo,int $userId,string $amount,string $reference,string $description,string $provider,string $providerRef): void
{
    walletCredit($pdo,$userId,$amount,$reference,$description,$provider,$providerRef);
}
