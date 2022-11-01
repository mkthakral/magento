<?php
    
    /**
     * Compilation includes configuration file
     */
    define('MAGENTO_ROOT', "../");

    $compilerConfig = MAGENTO_ROOT . '/includes/config.php';
    if (file_exists($compilerConfig)) {
        include $compilerConfig;
    }

    $mageFilename = MAGENTO_ROOT . '/app/Mage.php';
    $maintenanceFile = 'maintenance.flag';

    if (!file_exists($mageFilename)) {
        if (is_dir('downloader')) {
            header("Location: downloader");
        } else {
            echo $mageFilename." was not found";
        }
        exit;
    }

    if (file_exists($maintenanceFile)) {
        include_once dirname(__FILE__) . '/errors/503.php';
        exit;
    }

    require MAGENTO_ROOT . '/app/bootstrap.php';
    require_once $mageFilename;

 

    #ini_set('display_errors', 1);

    umask(0);

    /* Store or website code */
    $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

    /* Run store or run website */
    $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

    //Mage::run($mageRunCode, $mageRunType);
    Mage::init();



    $productId=$_REQUEST['productid'];
    
    $product=Mage::getModel('catalog/product')->load($productId);
    
    $productHeight = $product->getHeight();
    $productWidth = $product->getWidth();
    $productLength = $product->getLength();
    $productWeight = $product->getWeight();
    
    //Get Artist Details
    $protable= Mage::getSingleton('core/resource')->getTableName('marketplace_product');
	$sqlPaymentSystem1="SELECT * FROM ".$protable." WHERE mageproductid='".$productId."'";
	try {
        $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
		$result1= $chkSystem->fetchall();
    }
	catch (Exception $e){
	   echo $e->getMessage();
    }

	foreach($result1 as $res){
        $artisid=$res['userid'];
    }
    $artiscollection=Mage::getModel('customer/customer')->load($artisid);

    //$address = Mage::getModel('customer/address')->load($artiscollection->getentityId());
    $address = $artiscollection->getDefaultShippingAddress();
    $telephone = $address->getTelephone();
    $street = $address->getStreet();
    
    

    $artistZIP = $address->getPostcode();
    $artistCity = $address->getCity();
    $artistState = $address->getRegion();
    $artistCountry = "". Mage::app()->getLocale()->getCountryTranslation($address->getCountry());

    $data = [ 
        'ProductHeight' => $productHeight, 
        'ProductWidth' => $productWidth,
        'ProductDepth' => $productLength,
		'ProductPrice' => number_format($product->getPrice(), 2, null, ''),
        'ProductWeight' => round($productWeight,2),
        'ArtistCity' => $artistCity,
        'ArtistState' => $artistState,
        'ArtistCountry' => $artistCountry,
        'ArtistCountry' => $artistCountry,
        'ArtistZIP' => $artistZIP        
    ];
    header('Content-type: application/json');
    echo json_encode( $data );

?>