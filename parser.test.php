<?php

namespace levlabs;

require_once('parser.php');


function bool_to_str (?bool $val = false): string
{
  return ($val ? 'true' : 'false');
}


$test_list =
  [
    ['name' => 'Empty HTML.', 'html' => '', 'events' => 'start,done'],
    ['name' => 'Empty p tags', 'html' => '<p></p><p></p>', 'events' => 'start,open,p,{},false,close,p,open,p,{},false,close,p,done'],
    ['name' => 'Hidden Paragraph with Class Attribute', 'html' => '<p class="x" hidden></p>', 'events' => 'start,open,p,{"class":true,"hidden":true},false,close,p,done'],
  ];

$pad_len = strlen(count($test_list));
foreach ($test_list as $test_num => $test)
{
  $html = $test['html'];

  $parser = new_parser();
  $events = [];
  foreach ($parser['parse']($html) as $event)
  {
    $events[] = $event['type'];
    if (isset($event['name']))
    {
      $events[] = $event['name'];
    }
    $is_tag_open = $event['type'] === 'open';
    if (isset($event['attrs']) && $is_tag_open)
    {
      if (empty($event['attrs']))
      {
        $events[] = '{}';
      }
      else
      {
        $events[] = json_encode($event['attrs']);
      }
    }

    if ($is_tag_open)
    {
      $events[] = isset($event['self_closing']) ? bool_to_str($event['self_closing']) : 'false';
    }

    if (isset($event['text']))
    {
      $events[] = $event['text'];
    }
  }

  $test_name = $test['name'];

  $test_num = str_pad($test_num + 1, $pad_len, ' ', STR_PAD_LEFT);

  if (implode(',', $events) === $test['events'])
  {
    echo "Test $test_num passed: $test_name\n";
  }
  else
  {
    echo "Test $test_num failed: $test_name\n";
    echo "Expected: " . $test['events'] . "\n";
    echo "Actual:   " . implode(',', $events) . "\n";
  }
}