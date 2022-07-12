echo "---------- Install pngquant ----------"
apk add --no-cache pngquant

echo "---------- Install nodejs ------------"
apk add --no-cache nodejs

echo "---------- Install npm ---------------"
apk add --no-cache npm

echo "---------- Install chromium ----------"
apk add --no-cache chromium

echo "---------- Install chromium ----------"
apk add --no-cache wkhtmltopdf
ln -fs /usr/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf-amd64
ln -fs /usr/bin/wkhtmltoimage /usr/local/bin/wkhtmltoimage-amd64
