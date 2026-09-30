@props(['name'=>'reason','label'=>'Reason','placeholder'=>'Record what was checked and why'])
<div class="fx-layout-7 group"><label>{{ $label }}<textarea name="{{ $name }}" class="field" required minlength="10" maxlength="3000" placeholder="{{ $placeholder }}">{{ old($name) }}</textarea></label></div>
