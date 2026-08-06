<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryLifecycleStore
{
    public function __construct(private RecoveryStorageRoot $root, private ?RecoveryAtomicFileWriter $writer = null)
    { $this->writer ??= new RecoveryAtomicFileWriter(); }

    public function path(RecoveryIdentity $identity): string
    { return (new RecoveryStoragePathPolicy($this->root))->recoverySetRoot($identity) . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json'; }

    public function create(RecoveryLifecycleRecord $record): void
    { $path = $this->path($record->recoveryIdentity()); $this->ensureDirectory(dirname($path)); if (file_exists($path) || is_link($path)) throw new RecoveryLifecycleException('Recovery lifecycle record already exists.'); $this->writer->write($path, $this->encode($record)); }
    public function read(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { $path = $this->path($identity); if (is_link($path) || !is_file($path) || !is_readable($path)) throw new RecoveryLifecycleException('Recovery lifecycle record is unavailable.'); try { $data = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); if (!is_array($data)) throw new \RuntimeException(); $record = RecoveryLifecycleRecord::fromArray($data); if ($record->recoveryIdentity()->value() !== $identity->value()) throw new RecoveryLifecycleException('Recovery lifecycle identity does not match its storage namespace.'); return $record; } catch (\Throwable $e) { if ($e instanceof RecoveryLifecycleException) throw $e; throw new RecoveryLifecycleException('Recovery lifecycle record is malformed or unreadable.', 0, $e); } }
    public function save(RecoveryLifecycleRecord $record): void
    { $path = $this->path($record->recoveryIdentity()); $current = $this->read($record->recoveryIdentity()); if ($current->manifestIdentity() !== $record->manifestIdentity() || $current->operationIdentity() !== $record->operationIdentity()) throw new RecoveryLifecycleException('Recovery lifecycle identity changed.'); $this->replace($path, $this->encode($record)); }
    public function transition(RecoveryIdentity $identity, string $state, string $reason = ''): RecoveryLifecycleRecord
    { $record = $this->read($identity); $next = $record->transition($state, $reason); $this->save($next); return $next; }
    public function markMutationStarting(RecoveryIdentity $identity): RecoveryLifecycleRecord
    { $record = $this->read($identity); if ($record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete()) throw new RecoveryLifecycleException('Recovery is not ready for mutation.'); $next = $record->withMutationStarted(); $this->save($next); $verified = $this->read($identity); if (!$verified->mutationStarted()) throw new RecoveryLifecycleException('Mutation boundary was not durably verified.'); return $verified; }

    private function encode(RecoveryLifecycleRecord $record): string { return json_encode($record->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    private function ensureDirectory(string $path): void { if (is_link($path) || (file_exists($path) && !is_dir($path)) || (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path))) throw new RecoveryLifecycleException('Recovery lifecycle directory is unsafe.'); @chmod($path, 0700); }
    private function replace(string $path, string $contents): void
    { $dir=dirname($path); $tmp=$dir . DIRECTORY_SEPARATOR . '.recovery-lifecycle-' . bin2hex(random_bytes(16)) . '.tmp'; $h=@fopen($tmp,'xb'); if (!is_resource($h)) throw new RecoveryLifecycleException('Recovery lifecycle temporary file could not be created.'); try { $o=0; while($o<strlen($contents)){ $n=@fwrite($h,substr($contents,$o)); if(!is_int($n)||$n<1) throw new RecoveryLifecycleException('Recovery lifecycle write failed.'); $o+=$n; } if(!@fflush($h) || !function_exists('fsync') || !@fsync($h)) throw new RecoveryLifecycleException('Recovery lifecycle durability failed.'); @chmod($tmp,0600); } catch(\Throwable $e){fclose($h);@unlink($tmp);throw $e;} fclose($h); if(!@rename($tmp,$path)){@unlink($tmp);throw new RecoveryLifecycleException('Recovery lifecycle publication failed.');} if(@filesize($path)!==strlen($contents)||@hash_file('sha256',$path)!==hash('sha256',$contents)) throw new RecoveryLifecycleException('Recovery lifecycle read-back verification failed.'); }
}
