<?php

use Copot\Core\Database;

trait FormTransactional
{
    private static int $savepointCounter = 0;
    private function atomic(callable $operation): mixed
    {
        $connection=$this->database->connection(); $owns=!$connection->inTransaction(); $savepoint=null;
        if($owns)$connection->beginTransaction(); else { self::$savepointCounter++; $savepoint='form_manager_'.self::$savepointCounter.'_'.bin2hex(random_bytes(6)); $connection->exec('SAVEPOINT '.$savepoint); }
        try { $result=$operation(); if($owns)$connection->commit();else $connection->exec('RELEASE SAVEPOINT '.$savepoint); return $result; }
        catch(Throwable $failure){ if($owns){if($connection->inTransaction())$connection->rollBack();}elseif($connection->inTransaction()){$connection->exec('ROLLBACK TO SAVEPOINT '.$savepoint);$connection->exec('RELEASE SAVEPOINT '.$savepoint);}throw $failure; }
    }
}

final class FormDefinitionService
{
    use FormTransactional;
    public function __construct(private Database $database,private FormRepository $forms,private FormFieldRepository $fields,private FormDefinitionValidator $validator) {}
    public function create(array $input): Form
    {
        $definition=$this->validator->definition($input['name']??null,$input['status']??null,$input['fields']??[]);
        return $this->atomic(function()use($definition):Form{$id=$this->forms->create($definition['name'],$definition['status']);$this->fields->replace($id,$definition['fields']);return $this->forms->findById($id)??throw new RuntimeException('Created form is unavailable.');});
    }
    public function update(FormId|int $id,array $input,?string $expectedUpdatedAt=null):Form
    {
        $id=$id instanceof FormId?$id:new FormId($id);$definition=$this->validator->definition($input['name']??null,$input['status']??null,$input['fields']??[]);
        return $this->atomic(function()use($id,$definition,$expectedUpdatedAt):Form{if(!$this->forms->findById($id,true))throw new FormNotFoundException('Form is unavailable.');$this->forms->update($id,$definition['name'],$definition['status'],$expectedUpdatedAt);$this->fields->replace($id,$definition['fields']);return $this->forms->findById($id)??throw new FormNotFoundException('Form is unavailable.');});
    }
    public function disable(FormId|int $id):Form { $form=$this->forms->findById($id); if(!$form)throw new FormNotFoundException('Form is unavailable.'); return $this->update($form->id(),['name'=>$form->name(),'status'=>'disabled','fields'=>array_map(fn(FormField $field)=>['field_key'=>$field->key(),'label'=>$field->label(),'field_type'=>$field->type(),'sort_order'=>$field->sortOrder(),'is_required'=>$field->required(),'min_length'=>$field->minLength(),'max_length'=>$field->maxLength(),'options'=>array_map(fn(FormFieldOption $option)=>['option_value'=>$option->value(),'option_label'=>$option->label(),'sort_order'=>$option->sortOrder()],$field->options())],$this->fields->forForm($form->id()))],$form->updatedAt()); }
    public function delete(FormId|int $id):void { $id=$id instanceof FormId?$id:new FormId($id);$this->atomic(function()use($id):void{if(!$this->forms->findById($id,true))throw new FormNotFoundException('Form is unavailable.');if($this->forms->hasSubmissions($id,true))throw new FormInUseException('Forms with retained submissions must be disabled.');$this->forms->delete($id);}); }
}

final class FormSubmissionLifecycleService
{
    use FormTransactional;
    public function __construct(private Database $database,private FormRepository $forms,private FormFieldRepository $fields,private FormSubmissionRepository $submissions,private SubmissionValueValidator $validator) {}
    public function persist(FormId|int $formId,array $input):FormSubmission
    {
        $formId=$formId instanceof FormId?$formId:new FormId($formId);
        return $this->atomic(function()use($formId,$input):FormSubmission{$form=$this->forms->findById($formId,true);if(!$form)throw new FormNotFoundException('Form is unavailable.');if($form->status() !== 'published')throw new InvalidArgumentException('Only published forms accept submissions.');$fields=$this->fields->forForm($formId,true);$values=$this->validator->values($fields,$input);$id=$this->submissions->create($formId,$values);return $this->submissions->findById($id)??throw new RuntimeException('Created submission is unavailable.');});
    }
    public function markReviewed(FormSubmissionId|int $id):FormSubmission { return $this->transition($id,'reviewed'); }
    public function delete(FormSubmissionId|int $id):void{$id=$id instanceof FormSubmissionId?$id:new FormSubmissionId($id);$this->atomic(function()use($id):void{if(!$this->submissions->findById($id,true))throw new FormSubmissionNotFoundException('Submission is unavailable.');$this->submissions->delete($id);});}
    private function transition(FormSubmissionId|int $id,string $status):FormSubmission{$id=$id instanceof FormSubmissionId?$id:new FormSubmissionId($id);return $this->atomic(function()use($id,$status):FormSubmission{$submission=$this->submissions->findById($id,true);if(!$submission)throw new FormSubmissionNotFoundException('Submission is unavailable.');$this->submissions->updateStatus($id,$status);return $this->submissions->findById($id)??throw new FormSubmissionNotFoundException('Submission is unavailable.');});}
}
