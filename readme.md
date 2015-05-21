# Voilab TcTable for TCPDF

## Install

Via Composer

Create a composer.json file in your project root:
``` json
{
    "require": {
        "voilab/tctable": "1.*"
    }
}
```

``` bash
$ composer require voilab/tctable
```

## Usage
The goal of this library is not to replace a complex HTML table with row and
cell spans. It's mainly useful if you want to display loads of lines (for an
invoice, etc.) and be sure page breaks are in the right place. You can adapt
the number of widow lines (minimum of lines that must appear on the last page),
and use plugins to customize the flow (see below).

###  Basic usage
```php
$pdf = new \TCPDF();
$minRowHeight = 6; //mm

$tctable = new \voilab\tctable\TcTable($pdf, $minRowHeight);
$tctable->setColumns([
    'description' => [
        'isMultiLine' => true,
        'header' => 'Description',
        'width' => 100
        // check inline documentation to see what options are available.
        // Basically, it's everything TCPDF Cell, MultiCell and Image can eat.
    ],
    'quantity' => [
        'header' => 'Quantity',
        'width' => 20,
        'align' => 'R'
    ],
    'price' => [
        'header' => 'Price',
        'width' => 20,
        'align' => 'R'
    ]
]);

// get rows data
$rows = getMyDatasAsMyObjs();

$tctable->addBody($rows, function (\voilab\tctable\TcTable $table, \MyObj $row) {
    $change_rate = 0.8;
    // map row data to TcTable column definitions
    return [
        'description' => $row->getDescription(),
        'quantity' => $row->getQuantity(),
        'price' => $row->getPrice() * $change_rate
    ];
});

$pdf->Output('tctable.pdf', 'I');
```

### Plugins
#### Have a column that fit the remaining page width
```php
$tctable
    ->addPlugin(new \voilab\tctable\plugin\FitColumn('text'))
    ->addColumn('text', [
        'isMultiLine' => true,
        'header' => 'Text'
        // no need to set width here, the plugin will calculate it for us,
        // depending on the other columns width
    ]);
```

#### Stripe rows
```php
$tctable
    // set true to have the first line with colored background
    ->addPlugin(new \voilab\tctable\plugin\StripeRows(true));
```

#### Widows management
```php
// set the minimum elements you want to see on the last page (if any)
$nb = 4;
// set a footer margin. Useful when you have lot of lines, and a total as the
// last one. If you want the total to appears at least with 4 lines, you have
// to add to the pageBreakTrigger margin this line height: the footer
$mFooter = 10; // i.e: mm

$tctable->addPlugin(new \voilab\tctable\plugin\Widows($nb, $mFooter));
```

#### Advanced plugin: draw a subtotal for a column at end of each page
We can go further by calculating a sum for a column, and display the current
sum at the end of the page, and finally report it on the next page.
```php
<?php

namespace your\namespace;

use voilab\tctable\TcTable;
use voilab\tctable\Plugin;

class Report implements Plugin {

    // column on which we sum its value
    private $column;
    // the current calculated sum
    private $currentSum = 0;

    public function __construct($column) {
        $this->column = $column;
    }

    public function configure(TcTable $table) {
        $table
            // when launching the main process, reset sum at 0
            ->on(TcTable::EV_BODY_ADD, [$this, 'resetSum'])
            // after each added row, add the value to the sum
            ->on(TcTable::EV_ROW_ADDED, [$this, 'makeSum'])
            // when a page is added, draw the "subtotal" string
            ->on(TcTable::EV_PAGE_ADD, [$this, 'addSubTotal'])
            // after a page is added, draw the "sum from previous page" string
            ->on(TcTable::EV_PAGE_ADDED, [$this, 'addReport']);
    }

    public function resetSum() {
        $this->currentSum = 0;
    }

    public function makeSum(TcTable $table, $data) {
        $this->currentSum += $data[$this->column] * 1;
    }

    public function getSum() {
        return $this->currentSum;
    }

    public function addSubTotal(TcTable $table) {
        $pdf = $table->getPdf();
        $pdf->SetFont('', 'I');
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell($table->getColumnWidthUntil($this->column), $table->getColumnHeight(), "Sub-total:", false, false, 'R');
        $pdf->Cell($table->getColumnWidth($this->column), $table->getColumnHeight(), $this->currentSum, false, false, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '');
    }

    public function addReport(TcTable $table) {
        $pdf = $table->getPdf();
        $pdf->SetFont('', 'I');
        $pdf->SetTextColor(150, 150, 150);
        $table->getPdf()->Cell($table->getColumnWidthUntil($this->column), $table->getColumnHeight(), "Sum from previous page", 'B', false, 'R');
        $table->getPdf()->Cell($table->getColumnWidth($this->column), $table->getColumnHeight(), $this->currentSum, 'B', true, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '');
    }

}
```
And the TcTable
```php
$tctable->addPlugin(new \your\namespace\Report('total'));
```

### Custom events
TcTable triggers some events we can listen to. Plugins use them a lot. But you
can simply define events without the need of plugins. It allows us to add some
usefull methods.

#### Add headers on each new page
```php
use \voilab\tctable\TcTable;
// ... create tctable

$tctable
    // when a page is added, draw headers
    ->on(TcTable::EV_PAGE_ADDED, function(TcTable $t) {
        $t->addHeader();
    })
    // just before headers are drawn, set font style to bold
    ->on(TcTable::EV_HEADER_ADD, function(TcTable $t) {
        $t->getPdf()->SetFont('', 'B');
    })
    // after headers are drawn, reset the font style
    ->on(TcTable::EV_HEADER_ADDED, function(TcTable $t) {
        $t->getPdf()->SetFont('', '');
    });
```

### Renderer and body functions

When parsing data, you can define either a renderer function for each or some
columns, or an anonymous function when calling $tctable->addBody(). These
functions are called twice, because it is needed in the process for height
calculation. You need to take that into account in certain cases.

```php
$total = 0;
$tctable->addBody($rows, function (TcTable $t, $row, $index, $isHeightCalculation) use (&$total) {
    // if $height is true, it means this method is called when height
    // calculation is running. If we want to do a sum, we check first if
    // $isHeightCalculation is false, so it means the func is called during row
    // draw.
    if (!$isHeightCalculation) {
        $total += $row->getSomeValue();
    }
    // you still need to return the data regardless of $isHeightCalculation
    return [
        'description' => $row->getDescription(),
        'value' => $row->getSomeValue()
    ];
});

echo sprintf('Total: %d', $total);
```

The same idea applies to column renderers.

> *Note*
> In cases like the one above (creating a sum), you better should use plugins
> or events. With the event _TcTable::EV_ROW_ADDED_, you can do exactely the
> same thing without bothering with height calculation (see below).

```php
$total = 0;
$tctable
    ->on(TcTable::EV_ROW_ADDED, function (TcTable $t, $row) use (&$total) {
        $total += $row->getSomeValue();
    })
    ->addBody($rows, function (TcTable $t, $row) {
        return [
            'description' => $row->getDescription(),
            'value' => $row->getSomeValue()
        ];
    });

echo sprintf('Total: %d', $total);
```

### Optimizations
You can optimize the workflow if you know exactly the height of each row. You
can bypass the height calculation this way:
```php
$tctable->on(TcTable::EV_ROW_HEIGHT_GET, function (TcTable $t, $row, $index) {
    return 6.4; // or null for default calculation
});
```

If you want to change the way cell's height is calculated, you can override the
default behaviour this way:
```php
$tctable->on(TcTable::EV_CELL_HEIGHT_GET, function (TcTable $t, $column, $data, $row) {
    if ($column == 'specialColumn') {
        return 12.8;
    }
    // use default calculation
    return null;
});
```
> Remember that only multiline cells are checked for their height. The others
> aren't taken in the process.

### Custom drawing function
If you need to insert images or need to do very specific things with cell's
drawing, you can bypass the normal drawing function this way:
```php
$tctable->addColumn('special', [
    'header' => "Special column",
    'width' => 15,
    'drawFn' => function (TcTable $t, $data, array $definition, $column, $row) {
        $t->getPdf()->Image(); //configure TCPDF Image method your way
        // return false to draw the cell normally, if there's no image to
        // display, for example
    },
    'drawHeaderFn' => function (TcTable $t, $data, array $definition, $column, $row) {
        // same comments as above
    }
]);
```

## Testing
No test currently written...
``` bash
$ phpunit
```

## Security

If you discover any security related issues, please use the issue tracker.

## Credits

- [tafel](https://github.com/tafel)
- [voilab](https://github.com/voilab)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
