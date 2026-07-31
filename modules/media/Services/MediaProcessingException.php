<?php

class MediaProcessingException extends RuntimeException {}
class MediaProcessingValidationException extends MediaProcessingException {}
class MediaProcessingCapabilityException extends MediaProcessingException {}
class MediaProcessingStorageException extends MediaProcessingException {}
