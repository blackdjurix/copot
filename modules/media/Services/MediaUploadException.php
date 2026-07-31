<?php

class MediaUploadException extends RuntimeException {}
class MediaUploadValidationException extends MediaUploadException {}
class MediaStorageException extends MediaUploadException {}
class MediaDeliveryException extends RuntimeException {}
