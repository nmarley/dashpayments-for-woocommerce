# DashPayments for WooCommerce 

DashPayments for WooCommerce is a Wordpress plugin that enables WooCommerce merchants to accept [Dash](https://www.dash.org "Dash - Digital Cash") directly, without the need for a third-party payment processor.

* Uses BIP32 extended public (xpub) keys for address generation.
* Requires access to an [Insight-API-Dash](https://github.com/udjinm6/insight-api-dash) instance

### Requirements:

* An Electrum-Dash wallet for receiving payments
* WordPress 4.4.2+
* WooCommerce 2.5.2+
* PHP 5.5+ with valid extensions:
  - gmp
  - bcmath
  - gd
  - mcrypt
  - openssl
  - curl
  - json

### Developers: Building the plugin

Install composer using your package manager, then use ```composer install``` to fetch all dependencies and place them into 'vendor/'.

If desired, use 'zip' to package the directory into a zip file with the same name:

    cd .. && zip -r dashpay-woocommerce.zip dashpay-woocommerce/

### Installation and Activation

Extract the .zip file and copy or FTP the dashpay-woocommerce directory to the WordPress 'plugins' directory. Activate the plugin in the WordPress-admin console.

Navigate to WooCommerce -> Settings -> Checkout. Click the 'Dash' option at the top of the page. Paste your xpub key from Electrum-Dash into the box labeled "Dash BIP32 Extended Public Key", and click 'Save changes' at the bottom of the page.

If you see a message stating "Dash payment gateway is operational", you should be ready to accept payment in Dash.

Here's a YouTube video demonstrating the exact process I described above:

<https://www.youtube.com/watch?v=HFzMPBY1rAQ>

### **HIGHLY RECOMMENDED (READ THIS SECTION)**

It's highly recommended that you setup a cron job to handle background order processing. It's not technically required, but will catch things like if a user closes their browser before the payment gets processed.

For manual cron jobs, add this line to your crontab (replace <yourdomain.com> with your own WordPress site URL):

    * * * * * curl -s http://<yourdomain.com>/wp-cron.php?doing_wp_cron > /dev/null 2>&1

For CPANEL, run the command below every minute (replace <yourdomain.com> with your own WordPress site URL):

    curl -s http://<yourdomain.com>/wp-cron.php?doing_wp_cron > /dev/null 2>&1

### Contributions/Bugs/Issues

If you'd like to contribute to the project, please submit a pull request at this Github repository (please fork this project and submit a pull request using a feature-branch).

If you think you've found a bug, please file an issue at this Github repo (start by clicking the issues tab above).

### License

DashPayments for WooCommerce is released under the terms of the MIT license. See http://opensource.org/licenses/MIT.
