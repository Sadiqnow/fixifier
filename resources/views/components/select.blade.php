@props(['name','label','value'=>'','options'=>[]])
<div class="group"><label for="{{ $name }}">{{ $label }}</label><select class="field" id="{{ $name }}" name="{{ $name }}" required>@foreach($options as $key=>$text)<option value="{{ $key }}" @selected((string)old($name,$value) === (string)$key)>{{ $text }}</option>@endforeach</select></div>
