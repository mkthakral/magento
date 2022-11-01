<?php
	require_once('../../../app/Mage.php');
    Mage::app();

	require_once '../../../solr/solr-admin/vendor/autoload.php';
	use MicrosoftAzure\Storage\Queue\QueueRestProxy;
	use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
	use MicrosoftAzure\Storage\Queue\Models\CreateMessageOptions;

	$action = htmlspecialchars($_GET["action"]);
	$productJSON;
	
	if($action == "DELETE_ALL"){
		$productObj->productId = "NA";
	    $productJSON = json_encode($productObj);
	}else{
	
			$productId = htmlspecialchars($_GET["productId"]);
			
			//Load Product
			$product = Mage::getModel('catalog/product')->load($productId);

			//Product Category
			$categoryIds = $product->getCategoryIds();
			foreach($categoryIds as $categoryIds1) {
				$_category = Mage::getModel('catalog/category')->load($categoryIds1);
				$category_id = $_category->getId();
				$category_name = $_category->getName();
			}
			//Style and Category
			if($category_id == 3) {
				$productStyles=$product->getAttributeText("style");
				$productCategories=$product->getAttributeText("categories");
			}else {
				$productStyles=$product->getAttributeText("style_photography");
				$productCategories=$product->getAttributeText("categories_photography");
			}
			//Artist Id
			$protable= Mage::getSingleton('core/resource')->getTableName('marketplace_product');
			$sqlPaymentSystem1="SELECT * FROM ".$protable." WHERE mageproductid='".$productId."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_read')->query($sqlPaymentSystem1);
				$result1= $chkSystem->fetchall();
			}
			catch (Exception $e){
				echo $e->getMessage();
			}
			foreach($result1 as $res){
				$artistId=$res['userid'];
			}
			
			//Product Availability
			if($product->getData("re_licensing") == 1)
				$productAvailability = "Yes, contact the Artist directly";
			else
				$productAvailability = "No";
			
			//Load Artist
			$artist=Mage::getModel('customer/customer')->load($artistId);

			//Artist Name
			$artistName = $artist->getName();

			//Artist Image
			if($artist->getAvatar()==""){
				$artistImage = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .'customer/nopropimg.png';
			} else {
				$artistImage = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'customer'. $artist->getAvatar();
			}
			
			//Artist Details
			$artistCity = $artist->getData('city');
			$artistState = $artist->getData("state");
			$artistCountry = $artist->getData("country");
			$artistGender = Mage::getResourceSingleton('customer/customer')->getAttribute('gender_new')->getSource()->getOptionText($artist->getData('gender_new')); 
			$artistEthnicity = Mage::getResourceSingleton('customer/customer')->getAttribute('ethnicity')->getSource()->getOptionText($artist->getData('ethnicity')); 
			
			//Artist Education
            $artistEducation = strip_tags($artist->getData('education'));
			if (strlen($artistEducation) > 150) {
				// truncate string
				$stringCut = substr($artistEducation, 0, 150);
				$endPoint = strrpos($stringCut, ' ');

				//if the string doesn't contain any space then it will cut without word basis.
				$artistEducation = $endPoint? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
				$artistEducation .= '...more';
			}
			if($artistEducation == ""){
				$artistEducation = "Not Available";
			}
			
			
			// Product image
			$productImage = Mage::helper('catalog/image')->init($product, 'image');

			//Product Image for Modal
			$productImageModal = $productImage->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE);
			list($modalImageWidth, $modalImageHeight) = getimagesize($productImageModal);
			if($modalImageHeight > 520){
				$productImageModal = $productImage->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(800,520);
			}elseif($modalImageWidth > 700){
				$productImageModal = $productImage->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(700,700);
			}else{
				$productImageModal = $productImage->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(700,625);
			}
			list($modalImageWidth, $modalImageHeight) = getimagesize($productImageModal);
				
			//Create Object
			$productObj->productId = $productId;
			$productObj->name = $product->getName();
			$productObj->image = "$productImage";
			$productObj->imageModal = "$productImageModal";
			$productObj->imageModalHeight = "$modalImageHeight";
			$productObj->imageModalWidth = "$modalImageWidth";
			$productObj->categoryId = $category_id;
			$productObj->categoryName = $category_name;
			$productObj->style = $productStyles;
			$productObj->categories = $productCategories;
			$productObj->availability = $productAvailability;
			$productObj->client = $product->getData("client");
			$productObj->productKeywords = $product->getName() . ' ' . $product->getData("search_keyword");

			$productObj->artistId = $artistId;
			$productObj->artistName="$artistName";
			$productObj->artistCity = $artistCity;
			$productObj->artistState = $artistState;
			$productObj->artistCountry = $artistCountry;
			$productObj->artistGender="$artistGender";
			$productObj->artistEthnicity = "$artistEthnicity";
			$productObj->artistImage = "$artistImage";
			$productObj->artistEducation = $artistEducation;
			$productObj->artistPortfolioLink = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'art_details?id='.$artistId;
			
			//Convert Object to JSON
			$productJSON = json_encode($productObj);

	}
	
	$FULL_JSON = '{"action":"'. $action. '",' . "product:" . $productJSON . '}' ;
	
	$connectionString = "DefaultEndpointsProtocol=http;AccountName=queue0storage0account;AccountKey=GVxogXY86YAZYLhiIF1w0+hThwhYNeQz/Jml1ShseDizXlk84Zc47ZpbHJVxaYy/HkKUuY1qFLPtp6X1+uoqQA==;EndpointSuffix=core.windows.net";
	$queueClient = QueueRestProxy::createQueueService($connectionString);
	
	$returnStatus;
	try{
		// Create message.
		$queueClient->createMessage("folyoz-queue", base64_encode($FULL_JSON));
		$returnStatus=$action;
	}catch(ServiceException $e){
		// Handle exception based on error codes and messages.
		// Error codes and messages are here:
		// https://msdn.microsoft.com/library/azure/dd179446.aspx
		$code = $e->getCode();
		$error_message = $e->getMessage();
		echo $code.": ".$error_message."<br />";
	}
	
	echo $returnStatus;

?>