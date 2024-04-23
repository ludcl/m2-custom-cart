# Jsantos_CustomCart module

The Jsantos_CustomCart module adds a custom cart to the store, visiting {base_url}/custom-cart.  
The Jsantos_CustomCart module is created as a technical challenge for FortNine and it's not developer with a real-life functionality.

## Installation details

The Jsantos_CustomCart should be installed placing the module in {magento-root-directory}/app/code,  
keeping the same directory structure ({magento-root-directory}/app/code/Jsantos/CustomCart).

For information about a module installation in Magento 2, see [Enable or disable modules](https://devdocs.magento.com/guides/v2.4/install-gde/install/cli/install-cli-subcommands-enable.html).

### Layouts

The module introduces layout handles in the `view/frontend/layout` directory.  
For more information about a layout in Magento 2, see the [Layout documentation](https://devdocs.magento.com/guides/v2.4/frontend-dev-guide/layouts/layout-overview.html).

### UI components

You can find this module UI components in the `view/frontend/ui_component` directory.  
For information about a UI component in Magento 2, see [Overview of UI components](https://devdocs.magento.com/guides/v2.4/ui_comp_guide/bk-ui_comps.html).

## Additional information

This module adds two new tables:
* jsantos_customcart
* jsantos_customcart_item
