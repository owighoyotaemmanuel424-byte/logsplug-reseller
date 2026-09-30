<?php
declare(strict_types=1);
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../crypto.php';
require_once __DIR__.'/../http.php';
require_once __DIR__.'/ProviderInterface.php';
require_once __DIR__.'/ProviderSchema.php';
require_once __DIR__.'/ProviderSecretStore.php';

final class ProviderRegistry
{
    /** @var array<string,ProviderInterface> */
    private static array $instances=[];

    public static function ids(): array
    {
        $dir=__DIR__;
        $ids=[];
        foreach (glob($dir.'/*Provider.php') ?: [] as $file) {
            $base=basename($file);
            if ($base==='ProviderInterface.php') continue;
            $id=strtolower((string)preg_replace('/Provider\.php$/','',$base));
            if ($id!=='') $ids[]=$id;
        }
        sort($ids);
        return $ids;
    }

    public static function schema(string $providerId): array { return ProviderSchema::load($providerId); }

    public static function get(string $providerId): ProviderInterface
    {
        $providerId=strtolower(trim($providerId));
        if (!in_array($providerId,self::ids(),true)) throw new InvalidArgumentException('Unknown provider.');
        if (isset(self::$instances[$providerId])) return self::$instances[$providerId];

        $class=''.ucfirst($providerId).'Provider';
        $file=__DIR__.'/'.$class.'.php';
        require_once $file;
        if (!class_exists($class)) throw new RuntimeException('Provider implementation missing.');
        $schema=self::schema($providerId);
        $config=self::config($providerId);
        $resolver=static function(string $secretName) use ($providerId,$schema): ?string {
            $stored=self::secret($providerId,$secretName);
            if ($stored!==null && $stored!=='') return $stored;
            $env=(string)($schema['secret_env']??'');
            if ($env!=='' && $secretName===(string)($schema['secret_name']??'api_key') && getenv($env)!==false) return (string)getenv($env);
            return null;
        };
        return self::$instances[$providerId]=new $class($config,$resolver);
    }

    public static function config(string $providerId): array
    {
        $schema=self::schema($providerId);
        $st=db()->prepare('SELECT config_key,config_value FROM provider_configs WHERE provider_id=?');
        $st->execute([$providerId]);
        $config=$schema;
        foreach($st->fetchAll() as $row) $config[(string)$row['config_key']]=(string)$row['config_value'];
        return $config;
    }

    public static function save(string $providerId,array $config,?string $secret): void
    {
        $schema=self::schema($providerId);
        $pdo=db();
        $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('INSERT INTO provider_configs(provider_id,config_key,config_value,updated_at)
                VALUES(?,?,?,CURRENT_TIMESTAMP)
                ON CONFLICT(provider_id,config_key) DO UPDATE SET config_value=EXCLUDED.config_value,updated_at=CURRENT_TIMESTAMP');
            foreach($config as $key=>$value){
                if ($key==='fields' || $key==='id' || $key==='label' || $key==='capabilities' || $key==='secret_env' || $key==='secret_label') continue;
                if (is_scalar($value)) $st->execute([$providerId,(string)$key,(string)$value]);
            }
            if ($secret!==null && $secret!=='') {
                $secretName=(string)($schema['secret_name']??'api_key');
                $pdo->prepare('INSERT INTO provider_secrets(provider_id,secret_name,secret_ciphertext,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(provider_id,secret_name) DO UPDATE SET secret_ciphertext=EXCLUDED.secret_ciphertext,updated_at=CURRENT_TIMESTAMP')->execute([$providerId,$secretName,ProviderSecretStore::encrypt($secret)]);
            }
            $pdo->commit();
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public static function secret(string $providerId,string $secretName='api_key'): ?string
    {
        $schema=self::schema($providerId);
        $st=db()->prepare('SELECT secret_ciphertext FROM provider_secrets WHERE provider_id=? AND secret_name=? LIMIT 1');$st->execute([$providerId,$secretName]);$cipher=$st->fetchColumn();$stored=is_string($cipher)&&$cipher!==''?ProviderSecretStore::decrypt($cipher):null;
        if($stored!==null&&$stored!=='') return $stored;
        $env=(string)($schema['secret_env']??'');
        return $env!=='' && $secretName===(string)($schema['secret_name']??'api_key') && getenv($env)!==false ? (string)getenv($env) : null;
    }
}
