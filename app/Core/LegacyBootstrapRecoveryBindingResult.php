<?php
namespace Copot\Core;
/** Typed recovery-binding evidence; it never grants mutation or readiness. */
final class LegacyBootstrapRecoveryBindingResult
{
    public const BOUND='BOUND'; public const INCOMPLETE='INCOMPLETE'; public const STALE='STALE'; public const UNVERIFIED='UNVERIFIED'; private string $identity;
    /** @param list<string> $diagnostics */
    public function __construct(private string $status, private string $handoffIdentity, private string $operationIdentity, private ?string $recoveryIdentity, private ?string $manifestIdentity, private ?string $crossReferenceIdentity, private ?string $actionLinkageIdentity, private array $diagnostics){
        if(!in_array($status,[self::BOUND,self::INCOMPLETE,self::STALE,self::UNVERIFIED],true))throw new \InvalidArgumentException('Bootstrap recovery binding status is invalid.'); foreach($diagnostics as $diagnostic)if(!is_string($diagnostic)||$diagnostic==='')throw new \InvalidArgumentException('Bootstrap recovery diagnostic is invalid.'); $this->identity=hash('sha256',json_encode([$status,$handoffIdentity,$operationIdentity,$recoveryIdentity,$manifestIdentity,$crossReferenceIdentity,$actionLinkageIdentity,$diagnostics],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    public function status():string{return $this->status;} public function bound():bool{return $this->status===self::BOUND;} public function identity():string{return $this->identity;} public function diagnostics():array{return $this->diagnostics;} public function mutationAuthorized():bool{return false;} public function operationalReady():bool{return false;}
    public function toArray():array{return ['binding_identity'=>$this->identity,'status'=>$this->status,'handoff_identity'=>$this->handoffIdentity,'operation_identity'=>$this->operationIdentity,'recovery_identity'=>$this->recoveryIdentity,'manifest_identity'=>$this->manifestIdentity,'cross_reference_identity'=>$this->crossReferenceIdentity,'action_linkage_identity'=>$this->actionLinkageIdentity,'diagnostics'=>$this->diagnostics,'mutation_authorized'=>false,'operational_ready'=>false];}
}
