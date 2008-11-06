<script type="text/javascript">
<!--
var urlAddObjToAss = "{$html->url('/areas/loadObjectToAssoc')}/{$object.id|default:0}/leafs/list_contents_for_section.tpl";
var priorityOrder = "{$priorityOrder|default:'asc'}";
var numContents = "{$contents.toolbar.size|default:0}";

{literal}

function addObjToAssoc(url, postdata) {
	$.post(url, postdata, function(html){
		if(priorityOrder == 'asc') {
			$("#areacontent li:last").after(html);
		} else {
			$("#areacontent li:first").before(html);
		}
		numContents = $("#areacontent li").size();
		if ($("#noContents"))
			$("#noContents").remove();
		$("#areacontent").fixItemsPriority();
		$("#areacontent").sortable("refresh");
		setRemoveActions();
	});
}

function setRemoveActions() {
	$("#areacontent").find("input[@type='button']").click(function() {
		$(this).parents("li").remove();
		numContents--;
		$("#areacontent").fixItemsPriority();
	});
}

$(document).ready(function() {

	$("#areacontent").sortable ({
		distance: 20,
		opacity:0.7,
		update: $(this).fixItemsPriority
	}).css("cursor","move");

	$("#contents_nav_leafs a").click(function() {
		$("#loading").show();
		$("#areacontentC").load(urlC, 
				{
					page:$(this).attr("rel"),
					dim:$("#dimContentsPage").val()
				}
				, function() {
			$("#loading").hide();
		});
	});
	
	$("#dimContentsPage").change(function() {
		$("#loading").show();
		$("#areacontentC").load(urlC, {dim:$(this).val()}, function() {
			$("#loading").hide();
		});
	});
	
	setRemoveActions();
	$(".modalbutton").click(function () {
		$(this).BEmodal();
	});
	
});
{/literal}
//-->
</script>
<div style="min-height:120px; margin-top:10px;">

{if !empty($contents.items)}
	
	<ul id="areacontent" class="bordered">
		{include file="inc/list_contents_for_section.tpl" objsRelated=$contents.items}
	</ul>		
	
	<div id="contents_nav_leafs">

		
	{if $contents.toolbar.prev > 0}
		<a href="javascript:void(0);" rel="{$contents.toolbar.prev}" class="graced" style="font-size:3em">‹</a>
	{/if}
	{if $contents.toolbar.next > 0}
		<a href="javascript:void(0);" rel="{$contents.toolbar.next}" class="graced" style="font-size:3em">›</a>
	{/if}
	
	dim:
	<select name="dimContentsPage" id="dimContentsPage">
		<option value="5"{if $dim == 5} selected{/if}>5</option>
		<option value="10"{if $dim == 10} selected{/if}>10</option>
		<option value="20"{if $dim == 20} selected{/if}>20</option>
		<option value="50"{if $dim == 50} selected{/if}>50</option>
		<option value="1000000"{if $dim == 1000000} selected{/if}>tutti</option>
	</select>
	</div>


{else}
	<ul id="areacontent" class="bordered">
		<li id="noContents"><em>{t}no contents{/t}</em></li>
	</ul>
{/if}


	<br />
	<input style="width:220px" type="button" rel="{$html->url('/areas/showObjects/')}{$object.id|default:0}/0/leafs" class="modalbutton" value=" {t}add contents{/t} " />
	
	<hr />
</div>	

	