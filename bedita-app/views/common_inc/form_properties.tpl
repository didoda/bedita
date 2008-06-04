

<div class="tab"><h2>{t}Properties{/t}</h2></div>

<fieldset id="properties">			
			
<table class="bordered">
		
	<tr>

		<th>{t}Status{/t}:</th>
		<td colspan="4">
			{html_radios name="data[status]" options=$conf->statusOptions 
			selected=$object.status|default:$conf->status separator="&nbsp;"}
		</td>

	</tr>


	{if !(isset($publication)) || $publication}

	<tr>
		<th>{t}Publication Schedule{/t}:</th>
		<td>
			<label>{t}Start{/t} </label>
			
			<input size="10" type="text" class="dateinput" name="data[start]" id="start" value="{if !empty($object.start)}{$object.start|date_format:$conf->date_format}{/if}"/>
			&nbsp;&nbsp;
			
			<label>{t}End{/t} </label>{strip}
			
			<input size="10" type="text" class="dateinput" name="data[end]" id="end" value="{if !empty($object.end)}{$object.end|date_format:$conf->date_format}{/if}"/>
		{/strip}
		</td>
	</tr>

	{/if}

	<tr>
		<th>{t}Main language{/t}:</th>
		<td>
		{assign var=object_lang value=$object.lang|default:$conf->defaultLang}
		<select name="data[lang]" id="main_lang">
			{foreach key=val item=label from=$conf->langOptions name=langfe}
			<option {if $val==$object_lang}selected="selected"{/if} value="{$val}">{$label}</option>
			{/foreach}
		</select>
		</td>
	</tr>
	
	{if isset($comments)}
	<tr>
		<th>{t}Comments{/t}:</th>
		<td><input type="radio"/>{t}No{/t} <input type="radio"/>{t}Yes{/t}</td>
	</tr>
	{/if}
</table>
	
</fieldset>
