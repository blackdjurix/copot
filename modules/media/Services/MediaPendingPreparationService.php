<?php

use Copot\Core\Session;

final class MediaPendingPreparationService
{
    private const SESSION_KEY = 'media.pending_preparations';
    private const TTL = 7200;

    public function __construct(private ?MediaProcessingService $processing, private MediaVariantRepository $variants, private MediaVariantFilesystemStorage $storage, private Session $session) {}

    public function prepare(int $userId, int $mediaId, MediaProcessingRequest $request): array
    {
        if ($userId < 1) throw new MediaProcessingValidationException('Pending preparation is not authorized.');
        $this->purgeExpired(); $this->discardSuperseded($userId, $mediaId); if (!$this->processing) throw new MediaProcessingValidationException('Pending preparation is unavailable.');
        $token = bin2hex(random_bytes(32));
        $variants = $this->processing->process($mediaId, $request, static fn (MediaProcessingRequest $one): string => MediaVariantKey::pendingSlot($token, (int) $one->resizeWidth()));
        $entries = array_map(static fn (MediaVariant $variant): array => ['key'=>$variant->variantKey(),'width'=>$variant->width(),'height'=>$variant->height()], $variants);
        $all = $this->all(); $all[$token] = ['user_id'=>$userId,'media_id'=>$mediaId,'profile'=>$request->profile(),'expires_at'=>time()+self::TTL,'variants'=>$entries]; $this->store($all);
        return ['token'=>$token,'id'=>$mediaId,'variants'=>$entries];
    }

    public function promote(string $token, int $userId, int $contentId, int $mediaId): array
    {
        $this->purgeExpired(); $all = $this->all(); $pending = $all[$token] ?? null;
        if (!is_array($pending) || $pending['user_id'] !== $userId || $pending['media_id'] !== $mediaId || $pending['profile'] !== 'content.featured' || $contentId < 1) throw new InvalidArgumentException('Featured Media preparation is unavailable.');
        $previous = [];
        foreach ($pending['variants'] as $entry) { $variant = $this->variants->find($mediaId, $entry['key']); if (!$variant) throw new InvalidArgumentException('Featured Media preparation is unavailable.'); $slot = MediaVariantKey::contentSlot($mediaId, $contentId, (int) $variant->width()); $this->variants->deleteDescriptor($mediaId, $variant->variantKey()); $old = $this->variants->saveOrReplaceDescriptor(['media_id'=>$mediaId,'variant_key'=>$slot,'storage_key'=>$variant->storageKey(),'mime_type'=>$variant->mimeType(),'extension'=>$variant->extension(),'byte_size'=>$variant->byteSize(),'width'=>$variant->width(),'height'=>$variant->height()]); if ($old && $old->storageKey() !== $variant->storageKey()) $previous[] = $old->storageKey(); }
        unset($all[$token]); $this->store($all); return array_values(array_unique($previous));
    }

    public function discard(string $token, int $userId): void { $all=$this->all(); $pending=$all[$token]??null; if(!is_array($pending)||$pending['user_id']!==$userId)return; foreach($pending['variants'] as $entry){$variant=$this->variants->deleteDescriptor((int)$pending['media_id'],(string)$entry['key']);if($variant)$this->storage->delete($variant->storageKey());}unset($all[$token]);$this->store($all); }

    public function variant(string $token, int $userId, string $key): ?MediaVariant { $pending=$this->all()[$token]??null; if(!is_array($pending)||$pending['user_id']!==$userId||($pending['expires_at']??0)<time())return null; foreach($pending['variants'] as $entry)if(hash_equals((string)$entry['key'],$key))return $this->variants->find((int)$pending['media_id'],$key); return null; }

    public function purgeExpired(): void { $all=$this->all();$changed=false;foreach($all as $token=>$pending){if(!is_array($pending)||($pending['expires_at']??0)<time()){if(is_array($pending))foreach($pending['variants']??[] as $entry){$variant=$this->variants->deleteDescriptor((int)($pending['media_id']??0),(string)($entry['key']??''));if($variant)$this->storage->delete($variant->storageKey());}unset($all[$token]);$changed=true;}}foreach($this->variants->expiredPending(date('Y-m-d H:i:s', time()-self::TTL)) as $variant){$removed=$this->variants->deleteDescriptor($variant->mediaId(),$variant->variantKey());if($removed)$this->storage->delete($removed->storageKey());}if($changed)$this->store($all); }
    private function discardSuperseded(int $userId, int $mediaId): void { foreach($this->all() as $token=>$pending)if(is_array($pending)&&$pending['user_id']===$userId&&$pending['media_id']===$mediaId)$this->discard((string)$token,$userId); }
    private function all(): array { $value=$this->session->get(self::SESSION_KEY,[]);return is_array($value)?$value:[]; }
    private function store(array $all): void { $this->session->set(self::SESSION_KEY,$all); }
}
