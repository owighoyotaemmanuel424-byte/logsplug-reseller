INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,description)
SELECT u.id,'adjustment',(w.balance*100)::bigint,'migration-legacy-wallet-'||u.id,'Opening balance migrated from legacy wallet'
FROM users u
JOIN wallets w ON w.user_id=u.id
WHERE w.balance > 0
  AND NOT EXISTS (
    SELECT 1 FROM wallet_transactions wt
    WHERE wt.user_id=u.id AND wt.reference='migration-legacy-wallet-'||u.id
  );
UPDATE users u SET wallet_balance=w.balance
FROM wallets w
WHERE w.user_id=u.id
  AND w.balance > 0
  AND NOT EXISTS (
    SELECT 1 FROM wallet_transactions wt
    WHERE wt.user_id=u.id AND wt.reference='migration-legacy-wallet-'||u.id
  );
