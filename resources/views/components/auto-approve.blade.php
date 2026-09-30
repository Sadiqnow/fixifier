@props(['value'])
<div class="group full"><input type="hidden" name="autoApprove" value="0"><label class="check"><input type="checkbox" name="autoApprove" value="1" @checked(old('autoApprove',$value))> Allow automatic approval after review window (requires signed-off policy and notifications)</label></div>
