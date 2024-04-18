<?php

namespace levlabs;

const TOKENIZER_EVENT = [
  "START" => 'start',
  "OPENING_TAG" => 'topen',
  "OPENING_TAG_END" => 'topen-end',
  "CLOSING_TAG" => 'tclose',
  "ATTRIBUTE" => 'attr',
  "COMMENT" => 'comment',
  "COMMENT_IN_TAG" => 'comment_in_tag',
  "TEXT" => 'text',
  "DONE" => 'done'
];
const TOKENIZER_STATE = [
  "TAG" => 1,
  "TEXT" => 2,
  "SCRIPT" => 3,
  "COMMENT" => 4
];

function apply_regex ($text, $i, $regex)
{
  preg_match($regex, $text, $match, PREG_OFFSET_CAPTURE, $i);
  if (!$match || $match[0][1] !== $i)
  {
    return null;
  }
  else
  {
    return [
      "length" => strlen($match[0][0]),
      "match" => $match,
    ];
  }
}

function get_opening_tag ($text, $i)
{
  return apply_regex($text, $i, "/(<(([a-z0-9-]+:)?[a-z0-9-]+))/i");
}

function get_closing_tag ($text, $i)
{
  // strict: /(<\/(([a-z0-9-]+:)?[a-z0-9-]+)>)/i
  //         /(<\/(([a-z0-9-]+:)?[a-z0-9-]+\s*)>)/i

  return apply_regex($text, $i, "/(<\/(([a-z0-9-]+:)?[a-z0-9-]+)\s*?>)/i");
}

function get_tag_end ($text, $i)
{
  return apply_regex($text, $i, "/(\s*(\/?>))/");
}

function get_text ($text, $i)
{
  return apply_regex($text, $i, "/([^<]+)/");
}

function get_attribute ($text, $i)
{
  return apply_regex($text, $i, "/(\s+(([a-z0-9\-_]+:)?[a-z0-9\-_]+)(\s*=\s*)?)/i");
}

function get_script ($text, $i)
{
  return apply_regex($text, $i, "/(([\s\S]*?)<\/script>)/");
}

function get_comment_open ($text, $i)
{
  return apply_regex($text, $i, "/(<!--)/");
}

function read_attr ($str, $i)
{
  //$regex = "/(\s*(\".*?\"|'.*?'|[^\s\"'>]+))/";
  $regex = "/(\s*(\".*?\"|'.*?'|[^\"'>]+))/";

  preg_match($regex, $str, $match, PREG_OFFSET_CAPTURE, $i);
  $val = $match[2][0];


  $quote = $val[0];
  $val_len = strlen($val);

  if ($quote === '"' || $quote === "'")
  {
    $next_quote_idx = strpos($val, $quote, 1);
    if ($next_quote_idx === false)
    {
      return [
        "length" => strlen($val),
        "value" => $val
      ];
    }
    else
    {
      return [
        "length" => strlen($val),
        "value" => substr($val, 1, $next_quote_idx - 1)
      ];
    }
  }
  else
  {
    $end_idx = strcspn($val, " \t\r\n>/", 1);

    return [
      "length" => strlen($val) + 2,
      "value" => $val
    ];
  }
}

function get_comment_open_in_tag ($text, $i)
{
  return apply_regex($text, $i, "/(\s*<!--)/");
}

function get_comment ($text, $i)
{
  return apply_regex($text, $i, "/(([\s\S]*?)-->)/");
}

function new_tokenizer ()
{
  $next = null;
  $cur_tag = null;
  $state = TOKENIZER_STATE["TEXT"];

  $tokenize = function ($text, $start = 0, $end = null) use (&$next, &$cur_tag, &$state, &$i) {

    $end = ($end === null) ? strlen($text) : $end;

    $i = $start;

    $handle_text = function ($text) use (&$i, &$state, &$cur_tag, &$next) {
      $is_bracket = $text[$i] === '<';

      if ($is_bracket && ($next = get_comment_open($text, $i)))
      {
        $i += $next["length"];
        $state = TOKENIZER_STATE["COMMENT"];
      }
      else if ($is_bracket && ($next = get_opening_tag($text, $i)))
      {
        $i += $next["length"];
        $cur_tag = $next["match"][2][0];
        yield ["type" => TOKENIZER_EVENT["OPENING_TAG"], "name" => $cur_tag];
        $state = TOKENIZER_STATE["TAG"];
      }
      else if ($is_bracket && ($next = get_closing_tag($text, $i)))
      {
        $i += $next["length"];
        yield ["type" => TOKENIZER_EVENT["CLOSING_TAG"], "name" => $next["match"][2][0]];
      }
      else if (($next = get_text($text, $i)))
      {
        $i += $next["length"];
        yield ["type" => TOKENIZER_EVENT["TEXT"], "text" => $next["match"][1][0]];
      }
      else
      {
        $ch = $text[$i];
        $i += 1;
        yield ["type" => TOKENIZER_EVENT["TEXT"], "text" => $ch];
      }
    };

    yield ["type" => TOKENIZER_EVENT["START"]];

    while ($i < $end)
    {
      switch ($state)
      {
        case TOKENIZER_STATE["TEXT"]:
          foreach ($handle_text($text) as $result)
          {
            yield $result;
          }
          break;
        case TOKENIZER_STATE["COMMENT"]:
          if (($next = get_comment($text, $i)))
          {
            $i += $next["length"];
            yield ["type" => TOKENIZER_EVENT["COMMENT"], "text" => $next["match"][2][0]];
            $state = TOKENIZER_STATE["TEXT"];
          }
          break;
        case TOKENIZER_STATE["SCRIPT"]:
          if ($next = get_script($text, $i))
          {
            $i += $next["length"];
            yield ["type" => TOKENIZER_EVENT["TEXT"], "text" => $next["match"][2][0]];
            yield ["type" => TOKENIZER_EVENT["CLOSING_TAG"], "name" => 'script'];
            $state = TOKENIZER_STATE["TEXT"];
          }
          else
          {
            yield ["type" => TOKENIZER_EVENT["TEXT"], "text" => substr($text, $i)];
            return true;
          }
          break;
        case TOKENIZER_STATE["TAG"]:
          if (($next = get_attribute($text, $i)))
          {
            $i += $next["length"];
            $name = $next["match"][2][0];
            if (isset($next["match"][4]))
            { // attr-value available
              $attr = read_attr($text, $i);
              $i += $attr["length"];
              yield ["type" => TOKENIZER_EVENT["ATTRIBUTE"], "name" => $name, "value" => $attr["value"]];
            }
            else
            { // no value available
              yield ["type" => TOKENIZER_EVENT["ATTRIBUTE"], "name" => $name];
            }
          }
          else if (($next = get_comment_open_in_tag($text, $i)))
          {
            $i += $next["length"];
            if (($next = get_comment($text, $i)))
            {
              $i += $next["length"];
              yield ["type" => TOKENIZER_EVENT["COMMENT_IN_TAG"], "text" => $next["match"][2][0]];
            }
          }
          else if (($next = get_tag_end($text, $i)))
          {
            $i += $next["length"];
            $token = $next["match"][2][0];
            yield ["type" => TOKENIZER_EVENT["OPENING_TAG_END"], "name" => $cur_tag, "token" => $token];
            $state = ($cur_tag === 'script') ? TOKENIZER_STATE["SCRIPT"] : TOKENIZER_STATE["TEXT"];
          }
          else
          {
            $state = TOKENIZER_STATE["TEXT"];
          }
          break;
        default:
          break;
      }
    }

    yield ["type" => TOKENIZER_EVENT["DONE"]];
  };

  return compact('tokenize');
}


/*$html = file_get_contents('srob.html');

foreach (tokenize($html) as $token)
{
  var_dump($token);
}*/