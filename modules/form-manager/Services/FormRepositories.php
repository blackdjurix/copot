<?php

use Copot\Core\Database;

final class FormRepository
{
    public function __construct(private Database $database) {}
    public function findById(FormId|int $id, bool $forUpdate = false): ?Form
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM forms WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $this->id($id)->value()]); $row = $statement->fetch(); return is_array($row) ? $this->hydrate($row) : null;
    }
    public function create(string $name, string $status): FormId
    {
        $statement = $this->database->connection()->prepare('INSERT INTO forms (name,status,created_at,updated_at) VALUES (:name,:status,NOW(),NOW())');
        $statement->execute(compact('name', 'status')); return new FormId((int) $this->database->connection()->lastInsertId());
    }
    public function update(FormId|int $id, string $name, string $status, ?string $expectedUpdatedAt = null): void
    {
        $sql = 'UPDATE forms SET name=:name,status=:status,updated_at=GREATEST(NOW(),DATE_ADD(updated_at,INTERVAL 1 SECOND)) WHERE id=:id';
        $parameters = ['id' => $this->id($id)->value(), 'name' => $name, 'status' => $status];
        if ($expectedUpdatedAt !== null) { $sql .= ' AND updated_at=:updated_at'; $parameters['updated_at'] = $expectedUpdatedAt; }
        $statement = $this->database->connection()->prepare($sql); $statement->execute($parameters);
        if ($statement->rowCount() !== 1) throw new FormStaleWriteException('Form was changed or is unavailable.');
    }
    public function delete(FormId|int $id): void
    {
        $statement = $this->database->connection()->prepare('DELETE FROM forms WHERE id=:id'); $statement->execute(['id' => $this->id($id)->value()]);
        if ($statement->rowCount() !== 1) throw new FormNotFoundException('Form is unavailable.');
    }
    public function hasSubmissions(FormId|int $id, bool $forUpdate = false): bool
    {
        $statement = $this->database->connection()->prepare('SELECT 1 FROM form_submissions WHERE form_id=:id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $this->id($id)->value()]); return $statement->fetchColumn() !== false;
    }
    public function allByStatus(string $status): array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM forms WHERE status=:status ORDER BY updated_at DESC,id DESC'); $statement->execute(['status' => $status]);
        return array_map(fn(array $row): Form => $this->hydrate($row), $statement->fetchAll());
    }
    /** @return array{items: Form[], total: int, limit: int, offset: int} */
    public function workspace(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);$where=[];$parameters=[];
        $search=trim((string)($filters['search']??''));$status=$filters['status']??null;
        if($search!==''){$where[]='name LIKE :search';$parameters['search']='%'.$search.'%';}
        if(in_array($status,FormDefinitionValidator::FORM_STATES,true)){$where[]='status=:status';$parameters['status']=$status;}
        $clause=$where===[]?'':' WHERE '.implode(' AND ',$where);$connection=$this->database->connection();
        $count=$connection->prepare('SELECT COUNT(*) FROM forms'.$clause);$count->execute($parameters);
        $statement=$connection->prepare('SELECT * FROM forms'.$clause.' ORDER BY updated_at DESC,id DESC LIMIT :limit OFFSET :offset');
        foreach($parameters as $key=>$value)$statement->bindValue(':'.$key,$value);$statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->bindValue(':offset',$offset,PDO::PARAM_INT);$statement->execute();
        return ['items'=>array_map(fn(array $row):Form=>$this->hydrate($row),$statement->fetchAll()),'total'=>(int)$count->fetchColumn(),'limit'=>$limit,'offset'=>$offset];
    }
    private function id(FormId|int $id): FormId { return $id instanceof FormId ? $id : new FormId($id); }
    private function hydrate(array $row): Form { return new Form(new FormId((int)$row['id']), (string)$row['name'], (string)$row['status'], (string)$row['created_at'], (string)$row['updated_at']); }
}

final class FormFieldRepository
{
    public function __construct(private Database $database) {}
    /** @return FormField[] */
    public function forForm(FormId|int $formId, bool $forUpdate = false): array
    {
        $id = $this->formId($formId); $sql = 'SELECT * FROM form_fields WHERE form_id=:form_id ORDER BY sort_order ASC,id ASC' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement=$this->database->connection()->prepare($sql); $statement->execute(['form_id'=>$id->value()]); $fields=[];
        foreach ($statement->fetchAll() as $row) { $fieldId=new FormFieldId((int)$row['id']); $fields[]=$this->hydrate($row,$this->options($fieldId)); }
        return $fields;
    }
    public function replace(FormId|int $formId, array $definitions): void
    {
        $formId=$this->formId($formId); $connection=$this->database->connection();
        $existing=$connection->prepare('SELECT id,field_key FROM form_fields WHERE form_id=:form_id FOR UPDATE'); $existing->execute(['form_id'=>$formId->value()]);
        $byKey=[]; foreach($existing->fetchAll() as $row) $byKey[(string)$row['field_key']]=(int)$row['id']; $wanted=[];
        if ($byKey !== []) $connection->prepare('UPDATE form_fields SET sort_order = sort_order + 1000001 WHERE form_id = :form_id')->execute(['form_id' => $formId->value()]);
        foreach ($definitions as $definition) {
            $wanted[$definition['field_key']]=true;
            $fieldParameters = array_intersect_key($definition, array_flip(['field_key', 'label', 'field_type', 'sort_order', 'is_required', 'min_length', 'max_length']));
            if (isset($byKey[$definition['field_key']])) {
                $fieldId = $byKey[$definition['field_key']];
                $update = $connection->prepare('UPDATE form_fields SET label=:label,field_type=:field_type,sort_order=:sort_order,is_required=:is_required,min_length=:min_length,max_length=:max_length,updated_at=GREATEST(NOW(),DATE_ADD(updated_at,INTERVAL 1 SECOND)) WHERE id=:id');
                $update->execute([...array_diff_key($fieldParameters, ['field_key' => true]), 'id' => $fieldId]);
                $connection->prepare('DELETE FROM form_field_options WHERE form_field_id=:id')->execute(['id'=>$fieldId]);
            }
            else { $insert=$connection->prepare('INSERT INTO form_fields (form_id,field_key,label,field_type,sort_order,is_required,min_length,max_length,created_at,updated_at) VALUES (:form_id,:field_key,:label,:field_type,:sort_order,:is_required,:min_length,:max_length,NOW(),NOW())'); $insert->execute(['form_id'=>$formId->value(),...$fieldParameters]); $fieldId=(int)$connection->lastInsertId(); }
            $option=$connection->prepare('INSERT INTO form_field_options (form_field_id,option_value,option_label,sort_order,created_at,updated_at) VALUES (:form_field_id,:option_value,:option_label,:sort_order,NOW(),NOW())');
            foreach ($definition['options'] as $value) $option->execute(['form_field_id'=>$fieldId,...$value]);
        }
        foreach($byKey as $key=>$fieldId) if (!isset($wanted[$key])) $connection->prepare('DELETE FROM form_fields WHERE id=:id')->execute(['id'=>$fieldId]);
    }
    /** @return FormFieldOption[] */
    private function options(FormFieldId $id): array
    {
        $statement=$this->database->connection()->prepare('SELECT * FROM form_field_options WHERE form_field_id=:id ORDER BY sort_order ASC,option_value ASC'); $statement->execute(['id'=>$id->value()]);
        return array_map(fn(array $row): FormFieldOption => new FormFieldOption($id,(string)$row['option_value'],(string)$row['option_label'],(int)$row['sort_order'],(string)$row['created_at'],(string)$row['updated_at']),$statement->fetchAll());
    }
    private function formId(FormId|int $id): FormId { return $id instanceof FormId ? $id : new FormId($id); }
    private function hydrate(array $row,array $options): FormField { return new FormField(new FormFieldId((int)$row['id']),new FormId((int)$row['form_id']),(string)$row['field_key'],(string)$row['label'],(string)$row['field_type'],(int)$row['sort_order'],(bool)$row['is_required'],$row['min_length']===null?null:(int)$row['min_length'],$row['max_length']===null?null:(int)$row['max_length'],$options,(string)$row['created_at'],(string)$row['updated_at']); }
}

final class FormSubmissionRepository
{
    public function __construct(private Database $database) {}
    public function create(FormId|int $formId, array $values): FormSubmissionId
    {
        $formId=$this->formId($formId); $connection=$this->database->connection();
        $statement=$connection->prepare("INSERT INTO form_submissions (form_id,status,created_at,updated_at) VALUES (:form_id,'new',NOW(),NOW())"); $statement->execute(['form_id'=>$formId->value()]); $id=new FormSubmissionId((int)$connection->lastInsertId());
        $insert=$connection->prepare('INSERT INTO form_submission_values (submission_id,form_field_id,field_key,field_label,field_type,value_text,value_label,created_at) VALUES (:submission_id,:form_field_id,:field_key,:field_label,:field_type,:value_text,:value_label,NOW())');
        foreach($values as $value) $insert->execute(['submission_id'=>$id->value(),...$value]); return $id;
    }
    public function findById(FormSubmissionId|int $id, bool $forUpdate=false): ?FormSubmission
    {
        $id=$this->id($id); $statement=$this->database->connection()->prepare('SELECT * FROM form_submissions WHERE id=:id LIMIT 1'.($forUpdate?' FOR UPDATE':'')); $statement->execute(['id'=>$id->value()]); $row=$statement->fetch(); return is_array($row)?$this->hydrate($row):null;
    }
    /** @return array{items: array<int, array<string, mixed>>, total: int, limit: int, offset: int} */
    public function workspace(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $limit=max(1,min($limit,100));$offset=max(0,$offset);$where=[];$parameters=[];
        $formId=$filters['form_id']??null;$status=$filters['status']??null;$search=trim((string)($filters['search']??''));
        if($formId instanceof FormId)$formId=$formId->value();if(is_int($formId)&&$formId>0){$where[]='s.form_id=:form_id';$parameters['form_id']=$formId;}
        if(in_array($status,['new','reviewed'],true)){$where[]='s.status=:status';$parameters['status']=$status;}
        if($search!==''){$where[]='f.name LIKE :search';$parameters['search']='%'.$search.'%';}
        $clause=$where===[]?'':' WHERE '.implode(' AND ',$where);$connection=$this->database->connection();
        $count=$connection->prepare('SELECT COUNT(*) FROM form_submissions s INNER JOIN forms f ON f.id=s.form_id'.$clause);$count->execute($parameters);
        $statement=$connection->prepare('SELECT s.id,s.form_id,f.name AS form_name,s.status,s.created_at,s.updated_at FROM form_submissions s INNER JOIN forms f ON f.id=s.form_id'.$clause.' ORDER BY s.updated_at DESC,s.id DESC LIMIT :limit OFFSET :offset');
        foreach($parameters as $key=>$value)$statement->bindValue(':'.$key,$value);$statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->bindValue(':offset',$offset,PDO::PARAM_INT);$statement->execute();
        return ['items'=>$statement->fetchAll(),'total'=>(int)$count->fetchColumn(),'limit'=>$limit,'offset'=>$offset];
    }
    public function updateStatus(FormSubmissionId|int $id,string $status): void
    {
        $statement=$this->database->connection()->prepare('UPDATE form_submissions SET status=:status,updated_at=GREATEST(NOW(),DATE_ADD(updated_at,INTERVAL 1 SECOND)) WHERE id=:id'); $statement->execute(['id'=>$this->id($id)->value(),'status'=>$status]); if($statement->rowCount()!==1) throw new FormSubmissionNotFoundException('Submission is unavailable.');
    }
    public function delete(FormSubmissionId|int $id): void
    {
        $statement=$this->database->connection()->prepare('DELETE FROM form_submissions WHERE id=:id'); $statement->execute(['id'=>$this->id($id)->value()]); if($statement->rowCount()!==1) throw new FormSubmissionNotFoundException('Submission is unavailable.');
    }
    private function values(FormSubmissionId $id): array
    {
        $statement=$this->database->connection()->prepare('SELECT * FROM form_submission_values WHERE submission_id=:id ORDER BY id ASC'); $statement->execute(['id'=>$id->value()]);
        return array_map(fn(array $row):FormSubmissionValue=>new FormSubmissionValue((int)$row['id'],$id,$row['form_field_id']===null?null:new FormFieldId((int)$row['form_field_id']),(string)$row['field_key'],(string)$row['field_label'],(string)$row['field_type'],(string)$row['value_text'],$row['value_label']===null?null:(string)$row['value_label'],(string)$row['created_at']),$statement->fetchAll());
    }
    private function hydrate(array $row): FormSubmission { $id=new FormSubmissionId((int)$row['id']); return new FormSubmission($id,new FormId((int)$row['form_id']),(string)$row['status'],$this->values($id),(string)$row['created_at'],(string)$row['updated_at']); }
    private function id(FormSubmissionId|int $id):FormSubmissionId{return $id instanceof FormSubmissionId?$id:new FormSubmissionId($id);} private function formId(FormId|int $id):FormId{return $id instanceof FormId?$id:new FormId($id);}
}

class FormNotFoundException extends RuntimeException {}
class FormStaleWriteException extends RuntimeException {}
class FormInUseException extends RuntimeException {}
class FormSubmissionNotFoundException extends RuntimeException {}
class FormSubmissionFieldValidationException extends InvalidArgumentException
{
    public function __construct(private string $fieldKey, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
    public function fieldKey(): string { return $this->fieldKey; }
}
