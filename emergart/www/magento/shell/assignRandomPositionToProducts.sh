MAGENTO_ROOT=/var/www/magento

PHP_FILE=shell/assignRandomPositionToProducts.php
PRODUCT_UPLOAD_FILE=app/design/frontend/default/emergart/template/marketplace/simpleproduct.phtml
PRODUCT_EDIT_FILE=app/design/frontend/default/emergart/template/marketplace/editsimpleproduct.phtml

PATH_PRODUCT_UPLOAD_FILE=$MAGENTO_ROOT/$PRODUCT_UPLOAD_FILE
PATH_PRODUCT_EDIT_FILE=$MAGENTO_ROOT/$PRODUCT_EDIT_FILE
PATH_PHP_FILE=$MAGENTO_ROOT/$PHP_FILE
LOG_FILE=$MAGENTO_ROOT/var/log/cron-random-product.log

touch $LOG_FILE
echo `date +%F:%T` " -------------" >> $LOG_FILE

echo `date +%F:%T` " Start" >> $LOG_FILE

sed -i 's/\/\/alert("We are updating our system/alert("We are updating our system/g' $PATH_PRODUCT_UPLOAD_FILE
sed -i 's/\/\/alert("We are updating our system/alert("We are updating our system/g' $PATH_PRODUCT_EDIT_FILE

php $PATH_PHP_FILE

sed -i 's/alert("We are updating our system/\/\/alert("We are updating our system/g' $PATH_PRODUCT_UPLOAD_FILE
sed -i 's/alert("We are updating our system/\/\/alert("We are updating our system/g' $PATH_PRODUCT_EDIT_FILE

echo `date +%F:%T` " End" >> $LOG_FILEeme