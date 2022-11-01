<?php
	require_once('../../app/Mage.php');
	Mage::app();
?>
<html>
<head><title>Folyoz Solr Frontend</title>
	<link rel="stylesheet" href="css/styles.css">
	<script src="js/jquery-3.4.1.min.js"></script>
	<script type="text/javascript" src="https://www.folyoz.com/skin/frontend/folyoz/default/js/lib/matchMedia.js"></script>
	<script type="text/javascript" src="https://www.folyoz.com/skin/frontend/folyoz/default/js/lib/matchMedia.addListener.js"></script>

</head>
<body>
<div class="row">
  <div class="column-left" >
    <h2>Filters</h2>
	<div id="applied-filters"></div>
	<br/>
	Result Count: <div id="document-count" style="display:inline"></div>
    <h5>Main Category</h5>
	<div id="facet-product-category"></div>
	<br/>
	<input type="text" id="input-search-box"/> <input type="button" value="Search" onclick="freeTextSearch();" />
	<h5>Style</h5>
	<select id="facet-product-attribute-style" class="facet" name="attributeStyle"><option value="select">..Select..</option></select>
	<h5>Sub Category</h5>
	<select id="facet-product-attribute-category" class="facet" name="attributeCategory"></select>
	<h5>Artist City</h5>
	<select id="facet-artist-city" class="facet" name="artistCity"></select>
	<h5>Artist State</h5>
	<select id="facet-artist-state" class="facet" name="artistState"></select>
	<h5>Artist Country</h5>
	<select id="facet-artist-country" class="facet" name="artistCountry"></select>
	<h5>Artist Gender</h5>
	<select id="facet-artist-gender" class="facet" name="artistGender"></select>
	<h5>Artist Ethnicity</h5>
	<select id="facet-artist-ethnicity" class="facet" name="artistEthnicity"></select>
  </div>
  <div class="column-right" style="">
    <h2>Products</h2>
    <p id="products"></p>
  </div>
</div>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">


<!-- Modal -->
  <div class="modal fade" id="myModal" role="dialog" style="display:none">
    <div class="modal-dialog modal-dialog-centered">
      <!-- Modal content-->
      <div class="modal-content">
        <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
			<div class="img-pop-left"><img id="modal-product-image" src=""/></div>
			<div class="img-pop-right">
					<span id="modal-pro-member"></span>
					<span id="modal-artist-name">Artist Name</span>
					<div>
						<span class="details-heading">Style/Medium:</span>
						<span id="modal-medium">Medium x, Medium x, Medium x, Medium x, Medium x </span>
					</div>
					<span id="modal-client">Client x, Client x, Client x, Client x, Client x</span>
                    <div>
						<span class="details-heading">Availability:</span>
						<span id="modal-availability">Available, Available, Available</span>
					</div>
					<div>
						<img class="c1" id="modal-artist-image"height="125px"; width="125px"/>
						<span class="c2" id="modal-artist-name">Artist Name</span>
					</div>
					<div  class="education">
						<span id="modal-artist-education">Eugene Smith is an illustrator born and raised in California now residing in Chicago, IL. He decided all that time spent doodling in notebooks...more</span>
					</div>	
					<div>
						<a target="_blank" id="modal-artist-portfolio-link" href="'.Mage::getBaseUrl().'art_details?id='.$artisid.'" class="link-btn">View Portfolio</a>
					</div>	
					<div class="match-outer" id="modal-matching-percentage">47%</div>						
				</div>
        </div>
      </div>
    </div>
  </div>
<div id="loader" style="display:none;"></div>
<script type="text/javascript">
var defaultFacets = ["categoryName", "attributeStyle","attributeCategory","artistGender","artistEthnicity","artistCity", "artistState", "artistCountry"];
var solrProtocol = "http"
var solrHost="localhost";
var solrport="8983";
var solrCollection="folyoz-products";
var solrHandler = "select"
var solrQueryAllProducts="*:*";
var solrSearchQuery=solrQueryAllProducts;
var solrQueryEnableFacet = "facet=true";
var solrParamFacetLimit = "facet.mincount=1";
var solrAppliedFilters="";
var solrSolrFreeSearchField = "keywords";
var appliedFilterSuffix = " (x)";
var numberOfProductsPerPage = 12;
var pageNumber = 0;
var paginationInProgress = false;
//var startPointerOfDocument = 0;
	
function getSolrURL(){
	var url = solrProtocol + "://" + solrHost + ":" + solrport + "/solr/" + solrCollection + "/" + solrHandler + "?" + "q=" + solrSearchQuery + "&" + solrQueryEnableFacet + '&' + solrParamFacetLimit + '&start=' + (pageNumber*numberOfProductsPerPage) + '&rows=' + numberOfProductsPerPage ;

	var allFacets = "";
	$.each(defaultFacets, function(index, facet){
	  allFacets = allFacets.concat('&facet.field=' + facet);
	});

	url = url.concat(allFacets);
	url = url.concat(solrAppliedFilters);

	return url;
}

function executeSolrQuery(){
	$.ajax({
	  url: getSolrURL(),
	  beforeSend: function( xhr ) {
		xhr.overrideMimeType( "text/plain; charset=x-user-defined" );
	  }
	})
	  .done(function( solrResponse ) {
		
		//Applied Filters
		$("#applied-filters").html("");
		var splitFilters = solrAppliedFilters.split("&fq=");
		
		$.each(splitFilters, function(index, value){
			if(value!="")
				$("#applied-filters").append("<a href='#' onClick='removeFilter(this)'>" + value + appliedFilterSuffix + "</a><br/>");
		});
		
		//Applied Search Term
		if(solrSearchQuery != solrQueryAllProducts){
			$("#applied-filters").append("<a href='#' onClick='removeSearchFilter()'>" + solrSearchQuery.replace(solrSolrFreeSearchField, "Search").split("*").join("") + appliedFilterSuffix + "</a><br/>");
		}

		//Parsing Response
		var parsedResponse = jQuery.parseJSON(solrResponse);
		
		//Updating LHS
		var numberOfResults = parsedResponse.response.numFound;
		jQuery('#document-count').html(numberOfResults);
		
		
		//Facet: Category
		var categoryFacetResponse = parsedResponse.facet_counts.facet_fields.categoryName;
		var category;
		$("#facet-product-category").html("");
		$.each(categoryFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				category = value;
			}else{
				$("#facet-product-category").append("<a class='facet_link' href='#' facet='categoryName' facetValue=\"" + category + "\" onClick='facetClick(this)' " + ">" + category + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Product Attribute Style
		var attributeStyleFacetResponse = parsedResponse.facet_counts.facet_fields.attributeStyle;
		var attributeStyle;
		$("#facet-product-attribute-style").html("");
		$("#facet-product-attribute-style").append("<option value='select'>Select</option>");
		$.each(attributeStyleFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				attributeStyle = value;
			}else{
			
				$('#facet-product-attribute-style').append('<option facet="attributeStyle" value="' + attributeStyle + '">' + attributeStyle + "  (" + value + ")" + '</option>');

				//$("#facet-product-attribute-style").append("<a class='facet_link' href='#' facet='attributeStyle' facetValue=\"" + attributeStyle + "\" onClick='facetClick(this)' " + ">" + attributeStyle + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Product Attribute Category
		var attributeCategoryFacetResponse = parsedResponse.facet_counts.facet_fields.attributeCategory;
		var attributeCategory;
		$("#facet-product-attribute-category").html("");
		$("#facet-product-attribute-category").append("<option value='select'>Select</option>");
		$.each(attributeCategoryFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				attributeCategory = value;
			}else{
			
				$('#facet-product-attribute-category').append('<option facet="attributeCategory" value="' + attributeCategory + '">' + attributeCategory + "  (" + value + ")" + '</option>');
				
				//$("#facet-product-attribute-category").append("<a class='facet_link' href='#' facet='attributeCategory' facetValue=\"" + attributeCategory + "\" onClick='facetClick(this)' " + ">" + attributeCategory + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Artist Gender
		var artistGenderFacetResponse = parsedResponse.facet_counts.facet_fields.artistGender;
		var artistGender;
		$("#facet-artist-gender").html("");
		$("#facet-artist-gender").append("<option value='select'>Select</option>");
		$.each(artistGenderFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				artistGender = value;
			}else{
				
				$('#facet-artist-gender').append('<option facet="artistGender" value="' + artistGender + '">' + artistGender + "  (" + value + ")" + '</option>');
				
				//$("#facet-artist-gender").append("<a class='facet_link' href='#' facet='artistEthnicity' facetValue=\"" + artistGender + "\" onClick='facetClick(this)' " + ">" + artistGender + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Artist Ethnicity
		var artistEthnicityFacetResponse = parsedResponse.facet_counts.facet_fields.artistEthnicity;
		var artistEthnicity;
		$("#facet-artist-ethnicity").html("");
		$("#facet-artist-ethnicity").append("<option value='select'>Select</option>");
		$.each(artistEthnicityFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				artistEthnicity = value;
			}else{
				
				$('#facet-artist-ethnicity').append('<option facet="artistEthnicity" value="' + artistEthnicity + '">' + artistEthnicity + "  (" + value + ")" + '</option>');
				
				//$("#facet-artist-ethnicity").append("<a class='facet_link' href='#' facet='artistEthnicity' facetValue=\"" + artistEthnicity + "\" onClick='facetClick(this)' " + ">" + artistEthnicity + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Artist City
		var artistCityFacetResponse = parsedResponse.facet_counts.facet_fields.artistCity;
		var artistCity;
		$("#facet-artist-city").html("");
		$("#facet-artist-city").append("<option value='select'>Select</option>");
		$.each(artistCityFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				artistCity = value;
			}else{
				
				$('#facet-artist-city').append('<option facet="artistCity" value="' + artistCity + '">' + artistCity + "  (" + value + ")" + '</option>');
				
				//$("#facet-artist-city").append("<a class='facet_link' href='#' facet='artistCity' facetValue=\"" + city + "\" onClick='facetClick(this)' " + ">" + city + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Artist State
		var artistStateFacetResponse = parsedResponse.facet_counts.facet_fields.artistState;
		var artistState;
		$("#facet-artist-state").html("");
		$("#facet-artist-state").append("<option value='select'>Select</option>");
		$.each(artistStateFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				artistState = value;
			}else{
				
				$('#facet-artist-state').append('<option facet="artistState" value="' + artistState + '">' + artistState + "  (" + value + ")" + '</option>');
				
				//$("#facet-artist-state").append("<a class='facet_link' href='#' facet='artistState' facetValue=\"" + state + "\" onClick='facetClick(this)' " + ">" + state + "  (" + value + ")</a><br/>");
			}
		});
		
		//Facet: Artist Country
		var artistCountryFacetResponse = parsedResponse.facet_counts.facet_fields.artistCountry;
		var artistCountry;
		$("#facet-artist-country").html("");
		$("#facet-artist-country").append("<option value='select'>Select</option>");
		$.each(artistCountryFacetResponse, function(index, value){
			if((index*1)%2 == 0){
				artistCountry = value;
			}else{
				
				$('#facet-artist-country').append('<option facet="artistCountry" value="' + artistCountry + '">' + artistCountry + "  (" + value + ")" + '</option>');
				
				//$("#facet-artist-country").append("<a class='facet_link' href='#' facet='artistCountry' facetValue=\"" + country + "\" onClick='facetClick(this)' " + ">" + country + "  (" + value + ")</a><br/>");
			}
		});
		
		//Updating RHS
		if(pageNumber == 0){
			//Clear products on page load and facet click
			$("#products").html("");
		}else{
		    //Hide loader in case of pagination
			paginationInProgress = false;
			$('#loader').hide();
		}
			
		
		var results = parsedResponse.response.docs;
		
		$.each(results, function(index, product){
			$("#products").append('<a href="javascript:void(0)" title="' + product.name + '" artisid="' + product.artistID + '" list="1" mageproductid="' + product.productId + '" class="product-image"><img height="200" width="200"  style="border: 1px solid black; padding: 10px; margin: 10px" src="' + product.image + '" ></img></a><input type="hidden" id="modal-data-input-product-' + product.productId  + '" product-image="' + product.imageModal + '" product-image-height="' + product.imageModalHeight + '" product-image-width="' + product.imageModalWidth + '" artist-name="' + product.artistName + '" product-style="' + product.attributeStyle + '" product-client="' + product.productClient + '" product-availability="' + product.productAvailabilty + '" artist-image="' + product.artistImage + '" artist-education="' + product.artistEducation + '" artist-portfolio-link="' + product.artistPortfolioLink + '" artist-director-matching-percentage="' + "" + '" />');
		});
		
		
	  }).fail(function (jqXHR, textStatus, errorThrown){
	  });
  }
  
  //On page load, first 12 products
  $(function() {
	executeSolrQuery();
	
  });
  
function facetClick(element){
	//alert($(element).attr("facet") + $(element).attr("facetValue"));
	//for pagination
	pageNumber = 0;
	//for applying facet
	var fq = $(element).attr("facet") + ":\"" + $(element).attr("facetValue") + "\"";
	//alert(fq);
    if(solrAppliedFilters.indexOf(fq) == -1){
		solrAppliedFilters = solrAppliedFilters + "&fq=" + fq;
		executeSolrQuery();
	}
}

function removeFilter(element){
	pageNumber = 0;
	var filterFQ = ($(element).html()).replace(appliedFilterSuffix,"");
	solrAppliedFilters = solrAppliedFilters.replace("&fq="+filterFQ,"");
	executeSolrQuery();
}

//pagination
$(window).scroll(function() {
      if(($(window).scrollTop() + $(window).height() >= $(document).height()) && ((pageNumber*numberOfProductsPerPage)+numberOfProductsPerPage) < $('#document-count').html()){
			
		   if(!paginationInProgress){
				pageNumber++;
				console.log(pageNumber);
				$('#loader').show();			
				paginationInProgress = true;
				executeSolrQuery();
			}
    }else{
		//alert("Scroll did not qualify: " + ((pageNumber*numberOfProductsPerPage)+numberOfProductsPerPage)  + " " + $('#document-count').html());
	}
});

$('.facet').change(function(element){ 
    //alert($(this).attr("name") + " : " + $(this).val());
	//for pagination
	pageNumber = 0;
	//for applying facet
	var fq = $(this).attr("name") + ":\"" + $(this).val() + "\"";
    if(solrAppliedFilters.indexOf(fq) == -1){
		solrAppliedFilters = solrAppliedFilters + "&fq=" + fq;
		executeSolrQuery();
	}
});

function freeTextSearch(){
	var searchItem = $('#input-search-box').val();
	if(searchItem == ""){
		alert("No search term mentioned");
	}else if(searchItem.indexOf(" ") == -1){
		//No space in search term
		solrSearchQuery = "keywords:*" + searchItem + "*";
		executeSolrQuery();
	}else{
		//Space in search term
		solrSearchQuery = "keywords:\"" + searchItem + "\"";
		executeSolrQuery();
	}
	$('#input-search-box').val("");
}

function removeSearchFilter(){
	solrSearchQuery = solrQueryAllProducts;
	$('#input-search-box').val("");
	executeSolrQuery();
}

jQuery('body').on('click', '.product-image', function () {
	jQuery('#loader').css('display','block');

	var productId = $(this).attr("mageproductid");
	var inputBoxId = '#modal-data-input-product-' + productId;
	
	//Fill modal data
	$('#modal-product-image').attr("src",$(inputBoxId).attr("product-image"));
	$('#modal-pro-member').html("");
	$('#modal-medium').html($(inputBoxId).attr("product-style"));
	$('#modal-client').html($(inputBoxId).attr("product-client"));
	$('#modal-availability').html($(inputBoxId).attr("product-availability"));
	$('#modal-artist-image').attr("src",$(inputBoxId).attr("artist-image"));
	$('#modal-artist-name').html($(inputBoxId).attr("artist-name"));
	$('#modal-artist-education').html(($(inputBoxId).attr("artist-education")).replace("...more","...<a target='_blank' href='" + $(inputBoxId).attr("artist-portfolio-link") + "'>more</a>"));
	$('#modal-artist-portfolio-link').attr("href", $(inputBoxId).attr("artist-portfolio-link"));
	$('#modal-matching-percentage').html("");
	
	var orgheight = $(inputBoxId).attr("product-image-height");
	var orgwidth = $(inputBoxId).attr("product-image-width");
	var padding = 0;
	if (window.matchMedia("(max-width: 700px)").matches) {
		jQuery('.modal-body').css('padding',0);
	}else{// desktop version
		jQuery('.modal-dialog').addClass('modal-lg');
		jQuery('.modal-dialog').css('max-width',orgwidth+345);
			
		// fixed size pop image and details		
		jQuery('.img-pop-left').css('width',orgwidth);
		jQuery('.img-pop-right').css('width',300);
	}

	var heightLeft = orgheight;
	var heightRight = jQuery('.img-pop-right').height();
	if(heightLeft > heightRight){
		jQuery('.modal-body').css('height',heightLeft);
	}else{
		jQuery('.modal-body').css('height',heightRight);
	}

	jQuery('#myModal').modal('show');
	jQuery('#loader').css('display','none');
	
});

if (!window.matchMedia("(max-width: 700px)").matches) {
	jQuery("body").on('shown.bs.modal','#myModal', function(){
		setTimeout(function(){
		var hei = jQuery('.modal-content').height();
		var imghei = jQuery('.modal-content img').height();
		var dialog = parseInt(jQuery('.modal-dialog').css('max-width'));
		if(imghei < hei){
		padding = Math.round((hei - imghei)/2);
		jQuery('.modal-dialog').css('max-width',dialog+padding);
		jQuery('.img-pop-left').width(jQuery('.img-pop-left').width()+padding);
		jQuery('.img-pop-left').css('padding-left',padding-20);
		jQuery('.img-pop-left').css('padding-top',padding-20);
		jQuery('.img-pop-left').css('padding-bottom',padding);
		}
		}, 1000);
	});
}

jQuery('body').on('click', '.close', function () {
	jQuery('#pop-outer').css('display','none');
	jQuery('#image-pop').css('display','none');
});
jQuery('body').on('click', '.image-pop-overlay', function () {
	jQuery('#pop-outer').css('display','none');
	jQuery('#image-pop').css('display','none');
});
</script>

</body>
</html>