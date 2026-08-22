<?php
namespace Copot\Core;
class MediaUploadException extends \RuntimeException{} class MediaUploadValidationException extends MediaUploadException{} class MediaStorageException extends MediaUploadException{} class MediaDeliveryException extends \RuntimeException{} class MediaNotFoundException extends \RuntimeException{} class MediaInUseException extends \RuntimeException{}
