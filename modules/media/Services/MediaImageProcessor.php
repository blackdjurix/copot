<?php

interface MediaImageProcessor
{
    public function processorVersion(): string;
    public function normalizedDimensions(string $path, MediaProcessingFacts $facts): array;
    public function write(string $sourcePath, string $destinationPath, MediaProcessingFacts $sourceFacts, MediaProcessingRequest $request): MediaProcessingFacts;
}
