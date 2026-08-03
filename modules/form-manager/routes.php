<?php

use Copot\Core\Response;

foreach (['FormIds.php', 'FormRecords.php', 'FormDefinitionValidator.php', 'SubmissionValueValidator.php', 'FormRepositories.php', 'FormServices.php', 'FormPublicRequestValidator.php', 'FormSubmissionAttemptRepository.php', 'FormPublicSubmissionService.php'] as $file) {
    require_once __DIR__ . '/Services/' . $file;
}

$formRepository = new FormRepository($app->database());
$formFields = new FormFieldRepository($app->database());
$formService = new FormDefinitionService($app->database(), $formRepository, $formFields, new FormDefinitionValidator());
$formPublicService = new FormPublicSubmissionService($formRepository, $formFields, new FormSubmissionLifecycleService($app->database(), $formRepository, $formFields, new FormSubmissionRepository($app->database()), new SubmissionValueValidator()), new FormSubmissionAttemptRepository($app->database()), new FormDefinitionValidator(), new FormPublicRequestValidator());
$formAdmin = $app->adminUrl();
$formPath = $formAdmin->childUrl('forms');
$formPublicPath = static fn (int $id): string => '/forms/' . $id;

$app->adminNavigation()->addRequired('Forms', $formPath, ['admin.access', 'forms.view'], 'link', 36);

$formRequireAdmin = static function ($request, string $permission) use ($app, $formAdmin): mixed {
    if (!$app->auth()->check()) return Response::redirect($formAdmin->baseUrl());
    $user = $app->auth()->user();
    return !$user?->can('admin.access') || !$user->can($permission) ? $app->adminErrors()->response($request, 403) : $user;
};
$formValidateCsrf = static fn ($request): ?Response => $app->csrf()->validateOrReject($request) instanceof Response ? $app->adminErrors()->response($request, 419) : null;
$formRouteId = static function (mixed $value): ?int {
    if ((!is_string($value) && !is_int($value)) || preg_match('/^[1-9][0-9]*$/', (string) $value) !== 1) return null;
    $id = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($id) ? $id : null;
};
$formRenderView = static function (string $view, array $data = []): string {
    if (!in_array($view, ['list', 'form', 'submissions', 'submission'], true)) throw new RuntimeException('Form Admin view is unavailable.');
    $file = __DIR__ . '/views/admin/' . $view . '.php'; if (!is_file($file)) throw new RuntimeException('Form Admin view is unavailable.');
    extract($data, EXTR_SKIP); ob_start(); try { require $file; return (string) ob_get_clean(); } catch (Throwable $failure) { ob_end_clean(); throw $failure; }
};
$formRenderAdmin = static fn (string $title, string $content, $user, string $path, int $status = 200): Response => Response::html($app->adminPageRenderer()->render($title, $content, $user, $app->csrf()->token(), $path), $status);
$formToData = static function (?Form $form, array $fields = []): array {
    if (!$form) return ['id' => null, 'name' => '', 'status' => 'draft', 'updated_at' => null, 'fields' => []];
    return ['id' => $form->id()->value(), 'name' => $form->name(), 'status' => $form->status(), 'updated_at' => $form->updatedAt(), 'fields' => array_map(static fn (FormField $field): array => ['field_key' => $field->key(), 'label' => $field->label(), 'field_type' => $field->type(), 'sort_order' => $field->sortOrder(), 'is_required' => $field->required(), 'min_length' => $field->minLength(), 'max_length' => $field->maxLength(), 'options' => array_map(static fn (FormFieldOption $option): array => ['option_value' => $option->value(), 'option_label' => $option->label(), 'sort_order' => $option->sortOrder()], $field->options())], $fields)];
};
$formReadDefinition = static function ($request, array $base): array {
    $errors=[];$fieldErrors=[];$name=$request->post('name', '');$fields=$request->post('fields', []);
    if (!is_scalar($name)) $errors[]='Form name is invalid.';
    if (!is_array($fields)) { $errors[]='Field definitions are invalid.'; $fields=[]; }
    $readInt = static function (mixed $value, bool $nullable, string $label): int|null {
        if ($nullable && ($value === null || $value === '')) return null;
        if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException($label . ' is invalid.');
        $string=(string)$value; if (preg_match('/^[0-9]+$/', $string)!==1 || (string)(int)$string!==ltrim($string,'0') && $string!=='0') throw new InvalidArgumentException($label . ' is invalid.');
        return (int)$string;
    };
    $normalized=[];
    foreach ($fields as $index => $field) {
        if (!is_array($field)) { $fieldErrors[(string)$index][]='Field definition is invalid.'; continue; }
        try {
            $options=$field['options']??[]; if(!is_array($options)) throw new InvalidArgumentException('Select options are invalid.'); $normalizedOptions=[];
            foreach($options as $optionIndex=>$option){if(!is_array($option))throw new InvalidArgumentException('Select option is invalid.');$normalizedOptions[]=['option_value'=>is_scalar($option['option_value']??null)?(string)$option['option_value']:null,'option_label'=>is_scalar($option['option_label']??null)?(string)$option['option_label']:null,'sort_order'=>$readInt($option['sort_order']??null,false,'Option sort order')];}
            $required=$field['is_required']??0; if(!in_array($required,['0','1',0,1,false,true],true))throw new InvalidArgumentException('Required state is invalid.');
            $normalized[]=['field_key'=>is_scalar($field['field_key']??null)?(string)$field['field_key']:null,'label'=>is_scalar($field['label']??null)?(string)$field['label']:null,'field_type'=>is_scalar($field['field_type']??null)?(string)$field['field_type']:null,'sort_order'=>$readInt($field['sort_order']??null,false,'Field sort order'),'is_required'=>in_array($required,['1',1,true],true),'min_length'=>$readInt($field['min_length']??null,true,'Minimum length'),'max_length'=>$readInt($field['max_length']??null,true,'Maximum length'),'options'=>$normalizedOptions];
        } catch (InvalidArgumentException $failure) { $fieldErrors[(string)$index][]=$failure->getMessage(); }
    }
    if($fieldErrors!==[])$errors[]='Please correct the highlighted field definitions.';
    $base['name']=is_scalar($name)?(string)$name:'';$base['fields']=$normalized;
    return [$base,$errors,$fieldErrors];
};
$formFormResponse = static function ($request, $user, array $form, array $errors, array $fieldErrors, string $mode, int $status = 422) use ($formRenderView, $formRenderAdmin, $formPath, $app): Response {
    $content=$formRenderView('form',['form'=>$form,'errors'=>$errors,'fieldErrors'=>$fieldErrors,'formMode'=>$mode,'heading'=>$mode==='edit'?'Edit Form':'Create Form','submitLabel'=>$mode==='edit'?'Save changes':'Create Form','formAction'=>$mode==='edit'?$app->adminUrl()->childUrl('forms/'.(int)$form['id'].'/edit'):$formPath,'csrfToken'=>$app->csrf()->token(),'adminUrl'=>static fn(string $path=''):string=>$app->adminUrl()->childUrl($path)]);
    return $formRenderAdmin($mode==='edit'?'Edit Form':'Create Form',$content,$user,$request->path(),$status);
};
$formPublicResponse = static function ($request, int $id, array $values = [], array $errors = [], array $fieldErrors = [], ?string $nonce = null, bool $submitted = false, int $status = 200) use ($app, $formPublicService, $formPublicPath): Response {
    try {
        $definition = $formPublicService->published($id);
        $nonce ??= $formPublicService->issueNonce($app->session(), $id);
        $content = $app->viewRenderer()->renderFile($app->viewResolver()->resolve('form-manager::public.form'), [
            'form' => $definition['form'], 'fields' => $definition['fields'], 'values' => $values,
            'errors' => $errors, 'fieldErrors' => $fieldErrors, 'nonce' => $nonce,
            'csrfToken' => $app->csrf()->token(), 'action' => $formPublicPath($id), 'submitted' => $submitted,
        ], null, $definition['form']->name());
        return Response::html($content, $status);
    } catch (FormPublicUnavailableException) { return Response::html('Page not found.', 404); }
    catch (FormCorruptDefinitionException) { return Response::html('The form is temporarily unavailable.', 503); }
    catch (Throwable) { return Response::html('The form is temporarily unavailable.', 503); }
};
$app->router()->get('/forms/{id}', static function ($request, array $params) use ($app, $formRouteId, $formPublicResponse): Response {
    $id = $formRouteId($params['id'] ?? null); if ($id === null) return Response::html('Page not found.', 404);
    return $formPublicResponse($request, $id, [], [], [], null, $request->input('submitted') === '1');
});
$app->router()->post('/forms/{id}', static function ($request, array $params) use ($app, $formRouteId, $formPublicService, $formPublicResponse): Response {
    $id = $formRouteId($params['id'] ?? null); if ($id === null) return Response::html('Page not found.', 404);
    try {
        $formPublicService->published($id);
        $formPublicService->assertBodySize();
    } catch (FormPublicUnavailableException) { return Response::html('Page not found.', 404); }
    catch (FormCorruptDefinitionException) { return Response::html('The form is temporarily unavailable.', 503); }
    catch (FormPublicRequestException $failure) { return $formPublicResponse($request, $id, [], ['The submission could not be accepted.'], [], (string) $request->post('_form_nonce', ''), false, 422); }
    if (!$app->csrf()->validate($request)) return Response::html('Invalid CSRF token.', 419);
    $values = $request->post('values', []); $nonce = is_scalar($request->post('_form_nonce')) ? (string) $request->post('_form_nonce') : '';
    try {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';
        $formPublicService->submit($app->session(), $id, $nonce, $values, $request->post('_form_website'), (string) $address);
        return Response::redirect('/forms/' . $id . '?submitted=1');
    } catch (FormSubmissionFieldValidationException $failure) {
        return $formPublicResponse($request, $id, is_array($values) ? $values : [], ['Please correct the highlighted fields.'], [$failure->fieldKey() => $failure->getMessage()], $nonce, false, 422);
    } catch (FormPublicRequestException|FormPublicAntiAbuseException) {
        return $formPublicResponse($request, $id, is_array($values) ? $values : [], ['The submission could not be accepted.'], [], $nonce, false, 422);
    } catch (FormRateLimitException) { return Response::html('The submission could not be accepted at this time.', 429); }
    catch (InvalidArgumentException) { return $formPublicResponse($request, $id, is_array($values) ? $values : [], ['Please correct the submitted values.'], [], $nonce, false, 422); }
    catch (Throwable) { return Response::html('The form is temporarily unavailable.', 503); }
});
$formMessage = static function (Throwable $failure): string {
    return match (true) {
        $failure instanceof FormStaleWriteException => 'This form was changed elsewhere. Reload it before saving.',
        $failure instanceof FormInUseException => 'This form has retained submissions and cannot be deleted. Disable it instead.',
        $failure instanceof InvalidArgumentException => $failure->getMessage(),
        default => 'The form could not be saved. Please try again.',
    };
};

$app->router()->get($formPath, static function ($request) use ($app,$formRequireAdmin,$formRepository,$formRenderView,$formRenderAdmin,$formAdmin): Response {
    $user=$formRequireAdmin($request,'forms.view');if($user instanceof Response)return $user;
    $q=$request->input('q','');$status=$request->input('status');$perPage=$request->input('per_page',25);$page=$request->input('page',1);
    $filters=['q'=>is_scalar($q)?trim((string)$q):'','status'=>in_array($status,FormDefinitionValidator::FORM_STATES,true)?$status:null,'page'=>is_scalar($page)?max(1,(int)$page):1,'per_page'=>is_scalar($perPage)&& (int)$perPage>0?min(100,(int)$perPage):25];
    try{$workspace=$formRepository->workspace(['search'=>$filters['q'],'status'=>$filters['status']],$filters['per_page'],($filters['page']-1)*$filters['per_page']);$content=$formRenderView('list',['workspace'=>$workspace,'filters'=>$filters,'csrfToken'=>$app->csrf()->token(),'canManage'=>$user->can('forms.manage'),'canViewSubmissions'=>$user->can('forms.submissions.view'),'adminUrl'=>static fn(string $path=''):string=>$formAdmin->childUrl($path),'workspaceUrl'=>$formAdmin->childUrl('forms'),'notice'=>$request->input('notice'),'error'=>$request->input('error'),'hasSubmissions'=>static fn(Form $form):bool=>$formRepository->hasSubmissions($form->id())]);return $formRenderAdmin('Forms',$content,$user,$request->path());}catch(Throwable){return $formRenderAdmin('Forms','<div class="admin-alert admin-alert--danger" role="alert">Forms are temporarily unavailable.</div>',$user,$request->path(),503);}
});

$app->router()->get($formPath.'/create', static function ($request) use ($formRequireAdmin,$formFormResponse,$formToData): Response {$user=$formRequireAdmin($request,'forms.manage');return $user instanceof Response?$user:$formFormResponse($request,$user,$formToData(null),[],[],'create',200);});
$app->router()->post($formPath, static function ($request) use ($formRequireAdmin,$formValidateCsrf,$formReadDefinition,$formToData,$formService,$formFormResponse,$formPath,$formMessage): Response {$user=$formRequireAdmin($request,'forms.manage');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;[$data,$errors,$fieldErrors]=$formReadDefinition($request,$formToData(null));if($errors!==[])return $formFormResponse($request,$user,$data,$errors,$fieldErrors,'create');try{$formService->create($data);return Response::redirect($formPath.'?notice=created');}catch(Throwable $failure){return $formFormResponse($request,$user,$data,[$formMessage($failure)],$fieldErrors,'create');}});

$app->router()->get($formPath.'/{id}/edit', static function ($request,array $params) use ($app,$formRequireAdmin,$formRouteId,$formRepository,$formFields,$formToData,$formFormResponse): Response {$user=$formRequireAdmin($request,'forms.manage');if($user instanceof Response)return $user;$id=$formRouteId($params['id']??null);try{$form=$id?$formRepository->findById($id):null;}catch(Throwable){return $app->adminErrors()->response($request,503);}if(!$form)return $app->adminErrors()->response($request,404);return $formFormResponse($request,$user,$formToData($form,$formFields->forForm($form->id())),[],[],'edit',200);});
$app->router()->post($formPath.'/{id}/edit', static function ($request,array $params) use ($app,$formRequireAdmin,$formValidateCsrf,$formRouteId,$formRepository,$formReadDefinition,$formToData,$formService,$formFormResponse,$formPath,$formMessage): Response {$user=$formRequireAdmin($request,'forms.manage');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;$id=$formRouteId($params['id']??null);$current=$id?$formRepository->findById($id):null;if(!$current)return $app->adminErrors()->response($request,404);[$data,$errors,$fieldErrors]=$formReadDefinition($request,$formToData($current));$data['id']=$id;$data['status']=$current->status();if($errors!==[])return $formFormResponse($request,$user,$data,$errors,$fieldErrors,'edit');$expected=$request->post('expected_updated_at','');try{$formService->update($id,$data,is_scalar($expected)?(string)$expected:'');return Response::redirect($formPath.'?notice=updated');}catch(Throwable $failure){return $formFormResponse($request,$user,$data,[$formMessage($failure)],$fieldErrors,'edit');}});

foreach(['publish','disable'] as $action){$app->router()->post($formPath.'/{id}/'.$action, static function($request,array $params)use($app,$formRequireAdmin,$formValidateCsrf,$formRouteId,$formRepository,$formService,$formPath,$action):Response{$user=$formRequireAdmin($request,'forms.manage');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;$id=$formRouteId($params['id']??null);if(!$id||!$formRepository->findById($id))return $app->adminErrors()->response($request,404);try{$action==='publish'?$formService->publish($id):$formService->disable($id);}catch(InvalidArgumentException){return $app->adminErrors()->response($request,422);}catch(Throwable){return $app->adminErrors()->response($request,503);}return Response::redirect($formPath.'?notice='.($action==='publish'?'published':'disabled'));});}
$app->router()->post($formPath.'/{id}/delete', static function($request,array $params)use($app,$formRequireAdmin,$formValidateCsrf,$formRouteId,$formRepository,$formService,$formPath):Response{$user=$formRequireAdmin($request,'forms.manage');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;$id=$formRouteId($params['id']??null);if(!$id||!$formRepository->findById($id))return $app->adminErrors()->response($request,404);try{$formService->delete($id);}catch(FormInUseException){return Response::redirect($formPath.'?error='.rawurlencode('This form has retained submissions and cannot be deleted. Disable it instead.'));}catch(Throwable){return $app->adminErrors()->response($request,503);}return Response::redirect($formPath.'?notice=deleted');});

$submissionRepository = new FormSubmissionRepository($app->database());
$submissionService = new FormSubmissionLifecycleService($app->database(), $formRepository, $formFields, $submissionRepository, new SubmissionValueValidator());
$submissionPath = $formAdmin->childUrl('forms/submissions');
$submissionFilters = static function ($request) use ($formRouteId): array {$formId=$formRouteId($request->input('form_id'));$status=$request->input('status');$q=$request->input('q','');$perPage=$request->input('per_page',25);$page=$request->input('page',1);return ['form_id'=>$formId,'status'=>in_array($status,['new','reviewed'],true)?$status:null,'q'=>is_scalar($q)?trim((string)$q):'','per_page'=>is_scalar($perPage)&&(int)$perPage>0?min(100,(int)$perPage):25,'page'=>is_scalar($page)?max(1,(int)$page):1];};
$submissionMessage = static fn(Throwable $failure):string=>$failure instanceof InvalidArgumentException?$failure->getMessage():'The submission could not be updated. Please try again.';

$app->router()->get($submissionPath, static function($request)use($app,$formRequireAdmin,$submissionFilters,$submissionRepository,$formRepository,$formRenderView,$formRenderAdmin,$formAdmin,$submissionPath):Response{$user=$formRequireAdmin($request,'forms.submissions.view');if($user instanceof Response)return $user;$filters=$submissionFilters($request);try{$workspace=$submissionRepository->workspace(['form_id'=>$filters['form_id'],'status'=>$filters['status'],'search'=>$filters['q']],$filters['per_page'],($filters['page']-1)*$filters['per_page']);$forms=$formRepository->workspace([],100,0)['items'];$content=$formRenderView('submissions',['workspace'=>$workspace,'filters'=>$filters,'forms'=>$forms,'canDelete'=>$user->can('forms.submissions.delete'),'csrfToken'=>$app->csrf()->token(),'adminUrl'=>static fn(string $path=''):string=>$formAdmin->childUrl($path),'workspaceUrl'=>$submissionPath,'notice'=>$request->input('notice'),'error'=>$request->input('error')]);return $formRenderAdmin('Form submissions',$content,$user,$request->path());}catch(Throwable){return $formRenderAdmin('Form submissions','<div class="admin-alert admin-alert--danger" role="alert">Submissions are temporarily unavailable.</div>',$user,$request->path(),503);}});
$app->router()->get($submissionPath.'/{id}', static function($request,array $params)use($app,$formRequireAdmin,$formRouteId,$submissionRepository,$formRepository,$formRenderView,$formRenderAdmin,$formAdmin):Response{$user=$formRequireAdmin($request,'forms.submissions.view');if($user instanceof Response)return $user;$id=$formRouteId($params['id']??null);try{$submission=$id?$submissionRepository->findById($id):null;}catch(Throwable){return $app->adminErrors()->response($request,503);}if(!$submission)return $app->adminErrors()->response($request,404);$form=$formRepository->findById($submission->formId());if(!$form)return $app->adminErrors()->response($request,404);$content=$formRenderView('submission',['submission'=>$submission,'form'=>$form,'canDelete'=>$user->can('forms.submissions.delete'),'csrfToken'=>$app->csrf()->token(),'adminUrl'=>static fn(string $path=''):string=>$formAdmin->childUrl($path),'notice'=>$request->input('notice'),'error'=>$request->input('error')]);return $formRenderAdmin('Form submission',$content,$user,$request->path());});
$app->router()->post($submissionPath.'/{id}/review', static function($request,array $params)use($app,$formRequireAdmin,$formValidateCsrf,$formRouteId,$submissionRepository,$submissionService,$submissionPath,$submissionMessage):Response{$user=$formRequireAdmin($request,'forms.submissions.view');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;$id=$formRouteId($params['id']??null);if(!$id||!$submissionRepository->findById($id))return $app->adminErrors()->response($request,404);try{$submissionService->markReviewed($id);return Response::redirect($submissionPath.'/'.$id.'?notice=reviewed');}catch(InvalidArgumentException $failure){return Response::html('<div class="admin-alert admin-alert--danger" role="alert">'.htmlspecialchars($submissionMessage($failure),ENT_QUOTES,'UTF-8').'</div>',422);}catch(Throwable){return $app->adminErrors()->response($request,503);}});
$app->router()->post($submissionPath.'/{id}/delete', static function($request,array $params)use($app,$formRequireAdmin,$formValidateCsrf,$formRouteId,$submissionRepository,$submissionService,$submissionPath):Response{$user=$formRequireAdmin($request,'forms.submissions.delete');if($user instanceof Response)return $user;$csrf=$formValidateCsrf($request);if($csrf)return $csrf;$id=$formRouteId($params['id']??null);if(!$id||!$submissionRepository->findById($id))return $app->adminErrors()->response($request,404);try{$submissionService->delete($id);}catch(Throwable){return $app->adminErrors()->response($request,503);}return Response::redirect($submissionPath.'?notice=deleted');});
