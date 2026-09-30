<?php
declare(strict_types=1);
require_once __DIR__.'/auth_helpers.php';
require_once __DIR__.'/includes/providers/ProviderRegistry.php';
require_once __DIR__.'/includes/naira.php';
requireLogin();$user=getCurrentUser();$message='';$redirect='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $amount=trim((string)($_POST['amount']??''));
 try{
  $amount=nairaDecimal($amount);if(nairaKobo($amount)<100)throw new InvalidArgumentException('Minimum funding amount is ₦1.00.');
  $ref=createFundRequest((int)$user['id'],$amount);if($ref===null)throw new RuntimeException('Could not create funding request.');
  $r=ProviderRegistry::get('sprintpay')->createPayment(['amount'=>$amount,'reference'=>$ref,'email'=>(string)$user['email'],'callback_url'=>APP_URL.'/fund_callback.php'],$ref);
  if(!$r['ok'])throw new RuntimeException($r['error']);
  $redirect=(string)$r['data']['redirect_url'];
  header('Location: '.$redirect);exit;
 }catch(Throwable $e){$message=$e->getMessage();}
}
$businessName=getSetting('business_name')??BUSINESS_NAME;$pageTitle='Fund Wallet - '.$businessName;$layout='narrow';
require __DIR__.'/includes/head.php';require __DIR__.'/includes/header.php';
?>
<h1 class="page-title">Fund Wallet</h1><div class="auth-card">
<?php if($message):?><div class="alert alert-error"><p><?=htmlspecialchars($message)?></p></div><?php endif;?>
<form method="post"><div class="form-group"><label for="amount">Amount (NGN)</label><input id="amount" name="amount" type="text" inputmode="decimal" placeholder="0.00" required></div><button class="btn btn-primary" type="submit">Continue to payment</button></form>
<p class="auth-links"><a href="wallet.php">Back to wallet</a></p></div>
<?php require __DIR__.'/includes/footer.php';?>
