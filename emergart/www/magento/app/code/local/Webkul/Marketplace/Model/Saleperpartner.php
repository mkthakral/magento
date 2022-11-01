<?php

class Webkul_Marketplace_Model_Saleperpartner extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/saleperpartner');
    }
	
	public function salePayment($data)
	{
		$wholedata=$data->getParams('');
		$verifyrow = Mage::getModel('marketplace/saleperpartner')->load($wholedata['ID']);
		
			if($verifyrow->getAmountremain()>0){
			  $lastpayment = $verifyrow->getAmountremain();
				$collectionsave=Mage::getModel('marketplace/saleslist');
				$collectionsave->setMageproid(0);
				$collectionsave->setMageorderid(0);
				$collectionsave->setMagerealorderid(0);
				$collectionsave->setMagequantity(0);
				$collectionsave->setMageproownerid($verifyrow->getmageuserid());
				$collectionsave->setCpprostatus(1);
				$collectionsave->setTotalamount(0);
				$collectionsave->setMagebuyerid(0);
				$collectionsave->setMageproprice(0);
				$collectionsave->setMageproname('manual');
	            $collectionsave->setTotalcommision(0);
				$collectionsave->setActualparterprocost(-$verifyrow->getamountremain());
				$collectionsave->setClearedAt(date('Y-m-d H:i:s'));
				$collectionsave->save(); 		
			    $amountpaid=$verifyrow->getAmountrecived();
			    $totalrecived=$verifyrow->getAmountremain()+$amountpaid;
				$verifyrow->setAmountpaid($lastpayment);
				$verifyrow->setAmountrecived($totalrecived);
				$verifyrow->setAmountremain(0);
				$verifyrow->save();
				return 0;
			}	
	}

	public function masssalePayment($data)
	{
		$wholedata=$data->getParams();
		foreach ($wholedata['customer'] as $id) {
			$verifyrow = Mage::getModel('marketplace/saleperpartner')->load($id);
			if($verifyrow->getAmountremain()>0){
				$collectionsave=Mage::getModel('marketplace/saleslist');
				$collectionsave->setMageproid(0);
				$collectionsave->setMageorderid(0);
				$collectionsave->setMagerealorderid(0);
				$collectionsave->setMagequantity(0);
				$collectionsave->setMageproownerid($verifyrow->getmageuserid());
				$collectionsave->setCpprostatus(1);
				$collectionsave->setTotalamount(0);
				$collectionsave->setMagebuyerid(0);
				$collectionsave->setMageproprice(0);
				$collectionsave->setMageproname('manual');
				$collectionsave->setTotalcommision(0);
				$collectionsave->setActualparterprocost(-$verifyrow->getamountremain());
				$collectionsave->setClearedAt(date('Y-m-d H:i:s'));
				$collectionsave->save();

				$amountpaid=$verifyrow->getAmountrecived();
				$lastpayment=$verifyrow->getAmountremain();
				$totalrecived=$lastpayment+$amountpaid;
				$verifyrow->setAmountpaid($lastpayment);
				$verifyrow->setAmountrecived($totalrecived);
				$verifyrow->setAmountremain(0);
				$verifyrow->save();
			}
		}
		return 0;	
	}

}
