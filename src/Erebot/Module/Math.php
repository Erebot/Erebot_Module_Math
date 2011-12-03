<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

class   Erebot_Module_Math
extends Erebot_Module_Base
{
    protected $_trigger;
    protected $_handler;

    public function _reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');
            $this->_connection->removeEventHandler($this->_handler);
            $registry->freeTriggers($this->_trigger, $matchAny);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $trigger        = $this->parseString('trigger', 'math');
            $this->_trigger = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_trigger === NULL) {
                $fmt = $this->getFormatter(FALSE);
                throw new Exception($fmt->_('Could not register Math trigger'));
            }

            $this->_handler = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleMath')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf(
                        'Erebot_Interface_Event_Base_TextMessage'
                    ),
                    new Erebot_Event_Match_TextWildcard($trigger.' *', TRUE)
                )
            );
            $this->_connection->addEventHandler($this->_handler);
            $this->registerHelpMethod(array($this, 'getHelp'));
        }
    }

    protected function _unload()
    {
    }

    public function getHelp(Erebot_Interface_Event_Base_TextMessage $event, $words)
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $fmt        = $this->getFormatter($chan);
        $trigger    = $this->parseString('trigger', 'math');

        $bot        = $this->_connection->getBot();
        $moduleName = strtolower(get_class());
        $nbArgs     = count($words);

        if ($nbArgs == 1 && $words[0] == $moduleName) {
            $msg = $fmt->_(
                'Provides the <b><var name="trigger"/></b> command which '.
                'allows you to submit formulae to the bot for computation.',
                array('trigger' => $trigger)
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }

        if ($nbArgs < 2)
            return FALSE;

        if ($words[1] == $trigger) {
            $msg = $fmt->_(
                "<b>Usage:</b> !<var name='trigger'/> &lt;<u>formula</u>&gt;. ".
                "Computes the given formula and displays the result. ".
                "The four basic operators (+, -, *, /), parenthesis, ".
                "exponentiation (^) and modules (%) are supported.",
                array('trigger' => $trigger)
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }
    }

    public function handleMath(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Interface_Event_Base_TextMessage $event
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $formula    = $event->getText()->getTokens(1);
        $fmt        = $this->getFormatter($chan);

        try {
            $fp     = new Erebot_Module_Math_Lexer($formula);
            $msg = $fmt->_(
                '<var name="formula"/> = <b><var name="result"/></b>',
                array(
                    'formula' => $formula,
                    'result' => $fp->getResult(),
                )
            );
            $this->sendMessage($target, $msg);
        }
        catch (Erebot_Module_Math_DivisionByZeroException $e) {
            $this->sendMessage(
                $target,
                $fmt->_('Division by zero')
            );
        }
        catch (Erebot_Module_Math_ExponentTooBigException $e) {
            $this->sendMessage(
                $target,
                $fmt->_('Exponent is too big for computation')
            );
        }
        catch (Erebot_Module_Math_NegativeExponentException $e) {
            $this->sendMessage(
                $target,
                $fmt->_('^ is undefined for negative exponents')
            );
        }
        catch (Erebot_Module_Math_NoModulusOnRealsException $e) {
            $this->sendMessage(
                $target,
                $fmt->_('% is undefined on real numbers')
            );
        }
        catch (Erebot_Module_Math_SyntaxErrorException $e) {
            $this->sendMessage(
                $target,
                $fmt->_('Syntax error')
            );
        }
    }
}

