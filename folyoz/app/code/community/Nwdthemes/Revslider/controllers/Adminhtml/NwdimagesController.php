<?php

/**
 * Nwdthemes Revolution Slider Extension
 *
 * @package     Revslider
 * @author		Nwdthemes <mail@nwdthemes.com>
 * @link		http://nwdthemes.com/
 * @copyright   Copyright (c) 2014. Nwdthemes
 * @license     http://themeforest.net/licenses/terms/regular
 */

require_once 'Mage/Adminhtml/controllers/Cms/Wysiwyg/ImagesController.php';

class Nwdthemes_Revslider_Adminhtml_NwdimagesController extends Mage_Adminhtml_Cms_Wysiwyg_ImagesController {

    protected function _initAction() {
        $this->getStorage();
        $this->getStorage()->setUploadType( $this->getRequest()->getParam('type') );
        return $this;
    }

    public function indexAction()
    {
        $storeId = (int) $this->getRequest()->getParam('store');

        try {
            Mage::helper('nwdrevslider/images')->getCurrentPath();
        } catch (Exception $e) {
            Mage::helper('nwdrevslider')->logException($e);
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_initAction()->loadLayout('overlay_popup');
        $block = $this->getLayout()->getBlock('wysiwyg_images.js');
        if ($block) {
            $block->setStoreId($storeId);
        }

        // dynamically generate editor layout for backwards compatibility

        if ( ! $this->getLayout()->createBlock('uploader/multiple')) {
            $imagesUploaderBlock = $this->getLayout()
                ->createBlock('nwdrevslider/adminhtml_images_content_uploader', 'wysiwyg_images.uploader')
                ->setTemplate('cms/browser/content/uploader.phtml');
        } else {
            $additionalScriptsBlock = $this->getLayout()
                ->createBlock('core/template', 'additional_scripts')
                ->setTemplate('cms/browser/content/uploader.phtml');
            $imagesUploaderBlock = $this->getLayout()
                ->createBlock('nwdrevslider/adminhtml_cms_wysiwyg_images_content_uploader', 'wysiwyg_images.uploader')
                ->append($additionalScriptsBlock)
                ->setTemplate('media/uploader.phtml');
        }

        $imagesNewFolderBlock = $this->getLayout()
            ->createBlock('adminhtml/cms_wysiwyg_images_content_newfolder', 'wysiwyg_images.newfolder')
            ->setTemplate('cms/browser/content/newfolder.phtml');
        $imagesContentBlock = $this->getLayout()
            ->createBlock('adminhtml/cms_wysiwyg_images_content', 'wysiwyg_images.content')
            ->append($imagesUploaderBlock)
            ->append($imagesNewFolderBlock)
            ->setTemplate('cms/browser/content.phtml');
        $this->getLayout()
            ->getBlock('content')
            ->unsetChildren()
            ->append($imagesContentBlock);

        $this->renderLayout();
    }

    /**
     * Fire when select image
     */
    public function onInsertAction()
    {
        $helper = Mage::helper('nwdrevslider/images');
		$storeId = $this->getRequest()->getParam('store');

        $filename = $this->getRequest()->getParam('filename');
        $filename = $helper->idDecode($filename);

        Mage::helper('catalog')->setStoreId($storeId);
        $helper->setStoreId($storeId);

        $imageUrl = $helper->getImageHtmlDeclaration($filename, false);

        $data = array('image' => $imageUrl);
        $imagePath = $helper->getCurrentPath() . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($imagePath) && $imageSize = getimagesize($imagePath)) {
            $data['width'] = isset($imageSize[0]) ? $imageSize[0] : '';
            $data['height'] = isset($imageSize[1]) ? $imageSize[1] : '';
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }

    /**
     * Save current path in session
     *
     * @return Nwdthemes_Revslider_Adminhtml_ImagesController
     */
    protected function _saveSessionCurrentPath()
    {
        $this->getStorage()
            ->getSession()
            ->setCurrentPath(Mage::helper('nwdrevslider/images')->getCurrentPath());
        return $this;
    }

    /**
     * Delete file from media storage
     *
     * @return void
     */
    public function deleteFilesAction()
    {
        try {
            if (!$this->getRequest()->isPost()) {
                throw new Exception ('Wrong request.');
            }
            $files = Mage::helper('core')->jsonDecode($this->getRequest()->getParam('files'));

            /** @var $helper Mage_Cms_Helper_Wysiwyg_Images */
            $helper = Mage::helper('nwdrevslider/images');
            $path = $this->getStorage()->getSession()->getCurrentPath();
            foreach ($files as $file) {
                $file = $helper->idDecode($file);
                $_filePath = realpath($path . DS . $file);
                if (strpos($_filePath, realpath($path)) === 0 &&
                    strpos($_filePath, realpath($helper->getStorageRoot())) === 0
                ) {
                    $this->getStorage()->deleteFile($path . DS . $file);
                }
            }
        } catch (Exception $e) {
            Mage::helper('nwdrevslider')->logException($e);
            $result = array('error' => true, 'message' => $e->getMessage());
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Files upload processing
     */
    public function uploadAction()
    {
        try {
            $result = array();
            $this->_initAction();
            $targetPath = $this->getStorage()->getSession()->getCurrentPath();
            $result = $this->getStorage()->uploadFile($targetPath, $this->getRequest()->getParam('type'));
			$result['tmp_name'] = addslashes($result['tmp_name']);
			$result['path'] = addslashes($result['path']);
        } catch (Exception $e) {
            Mage::helper('nwdrevslider')->logException($e);
            $result = array('error' => $e->getMessage(), 'errorcode' => $e->getCode());
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

    }

    public function treeJsonAction()
    {
        try {
            $this->_initAction();
            $this->getResponse()->setBody(
                $this->getLayout()->createBlock('nwdrevslider/adminhtml_images_tree')
                    ->getTreeJson()
            );
        } catch (Exception $e) {
            Mage::helper('nwdrevslider')->logException($e);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array()));
        }
    }

    public function getStorage() {
        if (!Mage::registry('storage')) {
            $storage = Mage::getModel('nwdrevslider/images_storage');
            Mage::register('storage', $storage);
        }
        return Mage::registry('storage');
    }

}
