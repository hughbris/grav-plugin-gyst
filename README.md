# Sheet Storm Plugin

The **Sheet Storm** Plugin is for [Grav CMS](http://github.com/getgrav/grav).

It allows data [submitted from a form]http://github.com/getgrav/grav-plugin-form) to be saved as a row in a cloud spreadsheet. _Currently only Google Sheets is supported._

## Installation

Installing the Sheet Storm plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

> After either installation method, it is important to install the libraries required for your cloud spreadsheet provider. Presently only Google Sheets is supported, so please move into your plugin folder in a shell and type: `composer require google/apiclient:^2.0`. _(You need [PHP composer](https://getcomposer.org) for that to work, too.)_

### Dependencies

This plugin requires the [Form plugin](https://github.com/getgrav/grav-plugin-form) to provide anything useful.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install sheet-storm

This will install the Sheet Storm plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/sheet-storm`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `sheet-storm`. You can find these files on [GitHub](https://github.com/hughbris/grav-plugin-sheet-storm) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/sheet-storm

> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

### Admin Plugin

If you use the admin plugin, you can install directly through the admin plugin by browsing the `Plugins` tab and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/sheet-storm/sheet-storm.yaml` to `user/config/plugins/sheet-storm.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
authentication:
  providers:
    -
      name: 'google_sheets'
      path: '/LOCAL_API_KEYFILE_PATH' # configure this

Note that if you use the admin plugin, a file with your configuration, and named sheet-storm.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

## Usage

After configuring the plugin, add an action to your forms where you want to store data as a cloud spreadsheet row.

```yaml
    process: # parent property, included as context
        - sheet_row:
            sheet: 'YOUR_SPREADSHEET_ID'
```

## Credits

I will credit in a later commit #TODO.

This plugin comes out a [discussion on Grav Community Forum](https://discourse.getgrav.org/t/email-form-and-google-sheet/9586).

## To Do

- [ ] Make this better

