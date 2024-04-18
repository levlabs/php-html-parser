<?php

namespace levlabs;

require_once('tokenizer.php');

$test_list =
  [
    ['name' => 'Empty HTML.', 'html' => '', 'events' => 'start,done'],
    ['name' => 'Text Content', 'html' => 'hello world', 'events' => 'start,text,hello world,done'],
    ['name' => 'Opening Tag with Text', 'html' => '<i>hello world', 'events' => 'start,topen,i,topen-end,i,text,hello world,done'],
    ['name' => 'Text with Self-Closing Tag', 'html' => 'hello world <br>', 'events' => 'start,text,hello world ,topen,br,topen-end,br,done'],
    ['name' => 'Script Tag with Text', 'html' => '<script>alert("hello")</script>', 'events' => 'start,topen,script,topen-end,script,text,alert("hello"),tclose,script,done'],
    ['name' => 'Empty Script Tag', 'html' => '<script></script>', 'events' => 'start,topen,script,topen-end,script,text,,tclose,script,done'],
    ['name' => 'Nested Tags', 'html' => '<p><p></p><b>', 'events' => 'start,topen,p,topen-end,p,topen,p,topen-end,p,tclose,p,topen,b,topen-end,b,done'],
    ['name' => 'HTML Comment', 'html' => '<!-- Ein Kommentar -->', 'events' => 'start,comment, Ein Kommentar ,done'],
    ['name' => 'Some Entities', 'html' => '<p>Entities: &copy; 2024 &amp; &lt; &gt;</p>', 'events' => 'start,topen,p,topen-end,p,text,Entities: &copy; 2024 &amp; &lt; &gt;,tclose,p,done'],
    ['name' => 'Img Tag with Unquoted Attribute', 'html' => '<img src="test.jpg">', 'events' => 'start,topen,img,attr,src,test.jpg,topen-end,img,done'],
    ['name' => 'Multiline Attribute', 'html' => "<div class='c1\n c2'></div>", 'events' => "start,topen,div,attr,class,c1\n c2,topen-end,div,tclose,div,done"],

];

$pad_len = strlen(count($test_list));
foreach ($test_list as $test_num => $test)
{
  $html = $test['html'];

  $tokenizer = new_tokenizer();
  $events = [];
  foreach ($tokenizer['tokenize']($html) as $event)
  {
    $events[] = $event['type'];
    if (isset($event['name']))
    {
      $events[] = $event['name'];
    }

    if (isset($event['value']))
    {
      $events[] = $event['value'];
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
    echo "Actual: " . implode(',', $events) . "\n";
  }
}