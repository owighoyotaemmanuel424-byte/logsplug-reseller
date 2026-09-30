<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
$pdo=db();
$rows=$pdo->query("SELECT u.id,u.wallet_balance,COALESCE(SUM(CASE WHEN wt.type='debit' THEN -wt.amount_kobo ELSE wt.amount_kobo END),0) AS ledger_kobo FROM users u LEFT JOIN wallet_transactions wt ON wt.user_id=u.id GROUP BY u.id,u.wallet_balance")->fetchAll();
$st=$pdo->prepare('UPDATE users SET wallet_balance=? WHERE id=?');
foreach($rows as $row){
 $ledger=(int)$row['ledger_kobo'];$whole=intdiv($ledger,100);$frac=str_pad((string)($ledger%100),2,'0',STR_PAD_LEFT);$expected=$whole.'.'.$frac;
 if((string)$row['wallet_balance']!==$expected)$st->execute([$expected,(int)$row['id']]);
}
