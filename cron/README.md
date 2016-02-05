# Cron

Allows the calling of plugin and module scripts on a regular, scheduled basis. Unlike a true server cron that can be scheduled to the second, it is triggered by hits to the page that includes your cron tag. It should be used on templates that get regular traffic, or that are themselves accessed by a server cron.

## Usage

### `{exp:cron}`

#### Example Usage

Checks your moblogs during the first minute of every hour of every day:

```
{exp:cron minute="1" hour="*" day="*" month="*" module="moblog:check"}{/exp:cron}
```

Displays this content once every minute of every hour on the 1st, 15th, and 31st day of every month:

```
{exp:cron minute="*" hour="*" day="1,15,31" month="*"}
    Your content
{/exp:cron}
```

Calls the plugin Send Email and sends out your daily mailinglist:

```
{exp:cron minute="*" hour="*" day="1,15,31" month="*" plugin="send_email:mailinglist"}{/exp:cron}
```

#### Parameters

Date and time parameters:

- `minute=""` - (0-59), default is 0
- `hour=""` - (0-23, where 0 is midnight and 23 is 11pm), default is 0
- `day=""` - (1-31), default is *
- `month=""` - (1-12), default is *
- `weekday=""` - (0-6, where 0 Sunday and 1 is Monday), default is *

There are several ways of specifying multiple values in a time or date parameter:

- The comma (`,`) operator specifies a list of values, for example: "1,3,4,7,8"
- The dash (`-`) operator specifies a range of values, for example: "1-6", which is equivalent to "1,2,3,4,5,6"
- The comma and dash can be combined to specify multiple ranges and values. For example: 1-3,5,10-12, which is equivalent to 1,2,3,5,10,11,12
- The asterisk (`*`) operator specifies all possible values for a field. For example, an asterisk in the hour time field would be equivalent to 'every hour'.

Plugin/module parameters:

You can specify which function in a module or plugin to parse using one of these two parameters. You can only use only one of
the parameters per tag. The syntax is similar to what is used for a regular ExpressionEngine tag. The module/plugin name, a colon,
and then the name of the function being called in that module/plugin's class.

- `plugin="send_email:mailinglist"` - Calls the mailinglist function in the Send Email plugin
- `module="moblog:check"` - Calls the check function in the Moblog module


## Change Log

### 2.0.1

- Fixed a bug where the plugin wouldn't work after 2015.

### 2.0

- Updated plugin to be 3.0 compatible

### 1.1.1

- Fixed a bug that caused the plugin to stop working after December 2010

### 1.1

- Updated plugin to be 2.0 compatible

### 1.0.4

- Fixed a bug where ranges of time were not being parsed properly.

### 1.0.3

- Fixed a bug with cache permissions not being referenced correctly.

### 1.0.2

- Fixed a bug with the last possible check code having to do with the weekday.
Figured out how to make the plugin a little bit faster too.

### 1.0.1

- Fixed a minor little bug that occurred when the day parameter was set to *
and the month before this month had more days in it than the current month.
