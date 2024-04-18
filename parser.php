<?php

namespace levlabs;

require_once('tokenizer.php');

define('CLOSED_BY', [
  'p' => new_set('address,blockquote,article,aside,div,dl,fieldset,form,h1,h2,h3,h4,h5,h6,header,hgroup,hr,footer,main,nav,ol,p,pre,section,table,ul'),
  'option' => new_set('option,optgroup'),
  'optgroup' => new_set('optgroup'),
  'li' => new_set('li'),
  'dt' => new_set('dt,dd'),
  'dd' => new_set('dt,dd'),
  'rb' => new_set('rb,rt,rtc,rp'),
  'rt' => new_set('rb,rt,rtc,rp'),
  'rtc' => new_set('rb,rtc,rp'),
  'rp' => new_set('rb,rt,rtc,rp'),
  'thead' => new_set('tbody,tfoot'),
  'tbody' => new_set('tbody,tfoot'),
  'tfoot' => new_set('tbody'),
  'tr' => new_set('tr'),
  'td' => new_set('td,th'),
  'th' => new_set('td,th')
]);

define('SELF_CLOSING_TAG', new_set('br,img,link,input,area,base,col,command,embed,hr,keygen,meta,param,source,track,wbr'));

define('CLOSED_BY_PARENT_TAG', new_set('p,li,option,dd,rb,rt,rtc,rp,optgroup,tbody,tfoot,tr,td,th'));

const PARSER_EVENT = [
  'OPEN' => 'open',
  'CLOSE' => 'close',
  'COMMENT' => 'comment',
  'COMMENT_IN_TAG' => 'comment_in_tag',
  'TEXT' => 'text'
];

function is_self_closing_tag ($t): bool
{
  return array_key_exists($t, SELF_CLOSING_TAG);
}

function is_tag_closed_by_parent ($t): bool
{
  return array_key_exists($t, CLOSED_BY_PARENT_TAG);
}

function new_set ($comma_separated_text)
{
  $elements = explode(',', $comma_separated_text);
  $set = array_fill_keys($elements, true);
  return $set;
}

function is_tag_closed_by ($prev_tag, $cur_tag): bool
{
  return array_key_exists($cur_tag, (CLOSED_BY[$prev_tag] ?? []));
}

function canonicalize_newlines ($s): string
{
  return preg_replace(' / (\r\n | \r | \n) / ', "\n", $s);
}

function new_stack (): object
{
  return new class {
    private array $stack = [];

    function count (): int
    {
      return count($this->stack);
    }

    function push ($e): void
    {
      $this->stack[] = $e;
    }

    function pop ()
    {
      return array_pop($this->stack);
    }

    function peek ($n = 0)
    {
      return $this->stack[count($this->stack) - ($n + 1)] ?? null;

    }
  };
}

function new_parser (): array
{
  $started = false;
  $cur_tag = null;
  $tokenizer = new_tokenizer();
  $stack = new_stack();


  $parse = function ($text, $start = 0, $end = null, $eof = true) use (&$cur_tag, &$started, &$tokenizer, &$stack) {

    foreach ($tokenizer['tokenize']($text, $start, $end ?? strlen($text)) as $token_obj)
    {
      extract($token_obj);

      switch ($type)
      {
        case TOKENIZER_EVENT['COMMENT']:
        case TOKENIZER_EVENT['COMMENT_IN_TAG']:
          yield $token_obj;
          break;
        case TOKENIZER_EVENT['START']:
          if (!$started)
          {
            yield $token_obj;
            $started = true;
          }
          break;
        case TOKENIZER_EVENT['OPENING_TAG']:
          $cur_tag = ['name' => $name, 'attrs' => []];
          break;
        case TOKENIZER_EVENT['CLOSING_TAG']:

          $current = $stack->peek();
          $parent = $stack->peek(1);

          if ($current)
          {
            if ($current['name'] === $name)
            {
              $stack->pop();
              yield ['type' => PARSER_EVENT['CLOSE'], 'attrs' => $current['attrs'], 'name' => $current['name'], 'self_closing' => false];
            }
            else
            {
              if ($parent && $parent['name'] === $name && is_tag_closed_by_parent($current['name']))
              {
                $stack->pop();
                yield ['type' => PARSER_EVENT['CLOSE'], 'attrs' => $current['attrs'], 'name' => $current['name'], 'self_closing' => false];
                $stack->pop();
                yield ['type' => PARSER_EVENT['CLOSE'], 'attrs' => $parent['attrs'], 'name' => $parent['name'], 'self_closing' => false];
              }
            }
          }
          break;
        case TOKENIZER_EVENT['OPENING_TAG_END']:
          $prev_tag = $stack->peek();
          $self_closing = trim($token) === '/>' || is_self_closing_tag($name);

          if ($prev_tag && is_tag_closed_by($prev_tag['name'], $cur_tag['name']))
          {
            $stack->pop();
            yield ['type' => PARSER_EVENT['CLOSE'], 'attrs' => $prev_tag['attrs'], 'name' => $prev_tag['name'], 'self_closing' => false];
          }

          yield ['type' => PARSER_EVENT['OPEN'], 'name' => $cur_tag['name'], 'attrs' => $cur_tag['attrs'], 'self_closing' => $self_closing];

          if ($self_closing)
          {
            //yield ['type' => PARSER_EVENT['CLOSE'], 'attrs' => $cur_tag['attrs'], 'name' => $cur_tag['name'], 'self_closing' => $self_closing];
          }
          else
          {
            $stack->push($cur_tag);
          }
          break;
        case TOKENIZER_EVENT['TEXT']:
          yield ['type' => PARSER_EVENT['TEXT'], 'text' => canonicalize_newlines($text)];
          break;
        case TOKENIZER_EVENT['ATTRIBUTE']:
          if (isset($event['value']))
          {
            $cur_tag['attrs'][$name] = $event['value'];
          }
          else
          {
            $cur_tag['attrs'][$name] = true; // boolean attr
          }

          break;
      }
    }

    if ($eof)
    {
      while ($stack->count() > 0)
      {
        $prev = $stack->pop();
        yield ['type' => PARSER_EVENT['CLOSE'], 'name' => $prev['name'], 'self_closing' => false];
      }
      yield ['type' => TOKENIZER_EVENT['DONE']];
      $started = false;
    }
  };

  return compact('parse');
}
