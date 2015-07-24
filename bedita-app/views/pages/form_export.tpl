
<form action="{$html->url('/areas/export')}" method="post" name="exportForm" id="exportForm">
{$beForm->csrf()}

<input type="hidden" name="data[id]" value="{$objectId|default:''}"/>

<fieldset id="export" style="padding:10px 20px">

	<label>{t}export{/t} object {$objectId|default:''}</label>

	<div style="padding:0px 0 10px 0; margin:10px 0 10px 0; border-bottom:1px solid #dedede;">
		{foreach $conf->filters.export as $filter => $val}
			<input 
				onChange="javascript:$('.filterDetail').hide(); $('#{$filter|lower}').toggle()"
				name="data[type]" type="radio" value="{$filter}" />{$filter} &nbsp;
		{/foreach}
	</div>
	
</fieldset>

{foreach $conf->filters.export as $filter => $val}
	{if $val=="Epub3ExportFilter"}
	<div id="epub3" class="filterDetail" style="display:none; margin-top:0px; padding:20px 20px; margin:0px">
		<h2>{t}{$filter} export options{/t}</h2>

			<fieldset id="filen" style="padding:10px 0px">
				<div style="padding:0px 0 10px 0; margin:10px 0 10px 0; border-bottom:1px solid #dedede;">
					<input type="checkbox" checked=cheked /> package output
					&nbsp;&nbsp;&nbsp; <input type="checkbox" checked=cheked /> verbose (include all bedita:attributes)
				</div>
				filename: <input type="text" style="width:50%" name="data[filename]" value="bedita_export_{$objectId|default:''}.epub">
			</fieldset>

			<fieldset id="media" style="margin-top:10px; border-top:1px solid #dedede; padding:10px 0px">
				<label>{t}Include{/t}:</label>
				<ul style="margin-top:10px">
					<input name="data[type]" type="checkbox" checked=checked value="images" /> {t}Images{/t} &nbsp;
					<input name="data[type]" type="checkbox" value="audio" /> {t}Audio{/t} &nbsp;
					<input name="data[type]" type="checkbox" value="video" /> {t}Video{/t} &nbsp;
					<input name="data[type]" type="checkbox" value="applications" /> {t}Applications{/t} &nbsp;
					<input name="data[type]" type="checkbox" value="questions" /> {t}Exercises{/t} &nbsp;
				</ul>
			</fieldset>

			<fieldset id="partitions" style="margin-top:10px; border-top:1px solid #dedede; padding:10px 0px">
				<label syle="display:block">{t}Select a section for each partitions{/t}:</label>
				<br style="clear:both" />
				<div style="margin-top:10px; margin-right:10px; display:inline-block">
					<h4>Bodymatter</h4>
					<select style="width:100%">
						<option></option>
						<option>{t}none{/t}</option>
						<option>{t}all{/t}</option>
						<option>{t}home{/t}</option>
						<option>{t}Volume1{/t}</option>
						<option>{t}footer{/t}</option>
					</select>
				</div>
				<div style="margin-top:10px; margin-right:10px; display:inline-block">
					<h4>Frontmatter</h4>
					<select style="width:100%">
						<option></option>
						<option>{t}none{/t}</option>
						<option>{t}all{/t}</option>
							<option>{t}home{/t}</option>
						<option>{t}Volume1{/t}</option>
						<option>{t}footer{/t}</option>
					</select>
				</div>
				<div style="margin-top:10px; margin-right:10px; display:inline-block">
					<h4>Backmatter</h4>
					<select style="width:100%">
						<option></option>
						<option>{t}none{/t}</option>
						<option>{t}all{/t}</option>
						<option>{t}home{/t}</option>
						<option>{t}Volume1{/t}</option>
						<option>{t}footer{/t}</option>
					</select>
				</div>
			</fieldset>

			<fieldset id="toc" style="margin-top:10px; border-top:1px solid #dedede; padding:10px 0px">
				<label>{t}Table of contents{/t} (based on bodymatter):</label>
				<ul style="margin-top:10px">
					<input name="data[level]" type="radio" value="toc-level-1" />{t}Single level toc{/t} &nbsp;
					<input name="data[level]" type="radio" value="toc-level-2" />{t}Two level toc{/t} &nbsp;
					<input name="data[level]" type="radio" value="toc-level-3" />{t}Three level toc{/t} &nbsp;
					<input name="data[level]" type="radio" value="toc-level-4" />{t}Four level toc{/t} &nbsp;
				</ul>
			</fieldset>

			<fieldset id="namimg" style="margin-top:10px; border-top:1px solid #dedede; padding:10px 0px">
				<label>{t}Default structure naming{/t}:</label>
				<ul style="margin-top:10px">
					<select id="l1" style="display:inline-block; margin-right:20px;">
						<option></option>
						<option selected>{t}Volume{/t}</option>
						<option>{t}Chapter{/t}</option>
						<option>{t}Subchapter{/t}</option>
						<option>{t}Paragraph{/t}</option>
						<option>{t}Part{/t}</option>
						<option>{t}Lesson{/t}</option>
						<option>{t}other{/t}</option>
					</select>

					<select id="l2" style="display:inline-block; margin-right:20px;">
						<option></option>
						<option>{t}Volume{/t}</option>
						<option selected>{t}Chapter{/t}</option>
						<option>{t}Subchapter{/t}</option>
						<option>{t}Paragraph{/t}</option>
						<option>{t}Part{/t}</option>
						<option>{t}Lesson{/t}</option>
						<option>{t}other{/t}</option>
					</select>
					
					<select id="l3" style="display:inline-block; margin-right:20px;">
						<option></option>
						<option>{t}Volume{/t}</option>
						<option>{t}Chapter{/t}</option>
						<option>{t}Subchapter{/t}</option>
						<option>{t}Paragraph{/t}</option>
						<option>{t}Part{/t}</option>
						<option>{t}Lesson{/t}</option>
						<option>{t}other{/t}</option>
					</select>

					<select id="l4" style="display:inline-block; margin-right:20px;">
						<option></option>
						<option>{t}Volume{/t}</option>
						<option>{t}Chapter{/t}</option>
						<option>{t}Subchapter{/t}</option>
						<option>{t}Paragraph{/t}</option>
						<option>{t}Part{/t}</option>
						<option>{t}Lesson{/t}</option>
						<option>{t}other{/t}</option>
					</select>
				</ul>
			</fieldset>
			<fieldset style="margin-top:10px; border-top:1px solid #dedede; padding:10px 0px">
				<input type="submit" style="display:block; margin:20px; width:150px" value="{t}export{/t}" />
			</fieldset>
		</div>
	</div>
	{else}
	<div id="be-json" class="filterDetail" style="display:none; margin-top:0px; padding:20px 20px; margin:0px">
		<h2>{t}{$filter} export options{/t}</h2>
		<div style="padding:0px 0 10px 0; margin:10px 0 10px 0; border-bottom:1px solid #dedede;">
			<input type="checkbox" checked=1 /> recursive (include all children)
			&nbsp;&nbsp;&nbsp; 
			<input type="checkbox" /> include media files
			&nbsp;&nbsp;&nbsp;
			<input type="checkbox" /> verbose (include all attributes)

			&nbsp;&nbsp;&nbsp; 
			<input type="checkbox" /> compress output

		</div>
		filename: <input type="text" style="width:50%" name="data[filename]" value="bedita_export_{$objectId|default:''}">

		<input type="submit" style="display:block; margin:20px; width:150px" value="{t}export{/t}" />

		</div>
	{/if}
{/foreach}



</form>