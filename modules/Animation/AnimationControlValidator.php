<?php

namespace Sitchco\Parent\Modules\Animation;

/**
 * Checks every active animation's controls and keeps the ones that work.
 *
 * The definition rules for AnimationControl live here, apart from AnimationFrameworkModule, the
 * same way the config language lives in AnimationConfigResolver: the module discovers, memoizes
 * and logs, and this decides. It takes the active animations and returns the valid controls with
 * the list of problems found, so it never logs anything itself.
 *
 * Stateless, so unlike the resolver — which is built around one resolution's config and lookups —
 * it is a service the container autowires into the module.
 *
 * These are mistakes in an animation's code rather than in config, so the module logs them as
 * errors, as it does a duplicate animation key. As there, the first definition wins: animations
 * are visited in registration order and controls in declaration order, so which one is kept is
 * stable across requests.
 *
 * Strictly, the first *valid* definition wins. A definition dropped for another problem is
 * reported for that problem and never claims the name, so a later valid one of the same name is
 * kept rather than discarded with it: there is no reason to lose a control that works.
 */
class AnimationControlValidator
{
    /**
     * @param array<string, AnimationModule> $animations Active animations, keyed by animation key
     * @return array{controls: array<string, array<string, AnimationControl>>, problems: list<string>}
     */
    public function validate(array $animations): array
    {
        $valid = [];
        $attributeOwners = [];
        $problems = [];

        foreach ($animations as $key => $animation) {
            foreach ($animation->controls() as $index => $control) {
                if (!($control instanceof AnimationControl)) {
                    $problems[] = "{$key} / #{$index}: is not an AnimationControl. Dropping it.";
                    continue;
                }

                $context = "{$key} / {$control->name}";

                /* The name becomes the tail of a block attribute name and a key in config, so it is
                 held to something that is safe as both. `D`, because a bare `$` also matches before a
                 trailing newline. */
                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/D', $control->name)) {
                    $problems[] = "{$context}: the name must be camelCase letters and digits, starting with a lowercase letter. Dropping it.";
                    continue;
                }

                if (isset($valid[$key][$control->name])) {
                    $problems[] = "{$context}: is declared twice. Keeping the first valid one.";
                    continue;
                }

                /* A typo, not a broken control: on its own the control works, only without the setting
                   meant. Reported before the checks below, so a misspelled `optionFilter` is named
                   rather than seen only as a missing source. */
                if ($control->unknownOptions) {
                    $problems[] = sprintf(
                        '%s: does not know the option%s %s. Ignoring %s.',
                        $context,
                        count($control->unknownOptions) > 1 ? 's' : '',
                        implode(', ', array_map(fn($key) => "`{$key}`", $control->unknownOptions)),
                        count($control->unknownOptions) > 1 ? 'them' : 'it',
                    );
                }

                // The select shows it, and so does every config problem message about it.
                if (trim($control->label) === '') {
                    $problems[] = "{$context}: its label must not be empty. Dropping it.";
                    continue;
                }

                $problem = match ($control->type) {
                    'select' => $this->selectProblem($control),
                    'number' => $this->numberProblem($control),
                    default => null,
                };
                if ($problem !== null) {
                    $problems[] = "{$context}: {$problem} Dropping it.";
                    continue;
                }

                /* Distinct keys can still meet in one attribute name across the `Animation` that
                   joins key and name: `foo` + `bAnimationC` and `foo-animation-b` + `c` are both
                   fooAnimationBAnimationC — and two controls writing one attribute would overwrite
                   each other's saved values. */
                $attribute = AnimationFrameworkModule::attributeName($key, $control->name);
                if (isset($attributeOwners[$attribute])) {
                    $problems[] = "{$context}: its attribute \"{$attribute}\" is already used by {$attributeOwners[$attribute]}. Dropping it.";
                    continue;
                }

                $attributeOwners[$attribute] = $context;
                $valid[$key][$control->name] = $control;
            }
        }

        return ['controls' => $valid, 'problems' => $problems];
    }

    private function selectProblem(AnimationControl $control): ?string
    {
        // An empty filter name is no source: applyFilters('') resolves nothing.
        $hasFilter = $control->optionsFilter !== null && $control->optionsFilter !== '';
        if (($control->options !== null) === $hasFilter) {
            return 'a select needs exactly one of `options` or `optionsFilter`.';
        }

        if ($control->options !== null && !$this->isOptionList($control->options)) {
            return "`options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string, each value a distinct string or finite number, and any other key a string, bool or finite number.";
        }

        /* The editor cannot start on a value the select does not offer. An unoffered `''` is the
           worst of it: the select shows its first option as chosen, so choosing that option fires
           no change and it can never be stored. */
        $offered = $control->optionValues();
        if ($offered !== null && !in_array($control->default, $offered, true)) {
            return "its default \"{$control->default}\" is not one of its options.";
        }

        return null;
    }

    private function numberProblem(AnimationControl $control): ?string
    {
        /* json_encode() cannot write INF or NAN: it returns false, the inline script is left as
           `window.sitchco.animations = ;`, and the Animation panel disappears from every block.
           number() also takes its default as given, so a string can arrive here too. */
        if (!$this->isFiniteNumber($control->default)) {
            return 'its default must be a finite number.';
        }

        foreach (['min', 'max', 'step'] as $setting) {
            if ($control->$setting !== null && !$this->isFiniteNumber($control->$setting)) {
                return 'its min, max and step must be finite numbers.';
            }
        }

        if ($control->step !== null && $control->step <= 0) {
            return 'its step must be greater than zero.';
        }

        // Nothing could satisfy both, so the default below would be reported as out of range.
        if ($control->min !== null && $control->max !== null && $control->min > $control->max) {
            return "its min {$control->min} is greater than its max {$control->max}.";
        }

        if (!$control->inRange($control->default)) {
            return "its default {$control->default} is outside its range ({$control->describeRange()}).";
        }

        /* The editor cannot start on a value it would not keep: NumberControl rounds to its step
           grid whenever the input commits, so merely tabbing through the inspector would store a
           value nobody chose. The same rule selectProblem() applies to an unoffered default. */
        if (!$control->onStep($control->default)) {
            return "its default {$control->default} is off its step grid ({$control->describeStep()}).";
        }

        return null;
    }

    private function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }

    /**
     * A non-empty list of pairs, each with a non-empty string label and a string or finite number
     * value, no two values alike once cast to strings. The value is sent to the editor as a string
     * (AnimationControl::optionValues()), so a null would pose as the empty option, an array would
     * arrive as "Array", and `30` beside `'30'` would be two options the select cannot tell apart.
     *
     * Other keys are sent as declared, so each must hold something json_encode() can write: a
     * string, a bool or a finite number. An INF anywhere in the payload empties it entirely.
     */
    private function isOptionList(array $options): bool
    {
        if ($options === [] || !array_is_list($options)) {
            return false;
        }

        $seen = [];

        foreach ($options as $option) {
            if (!is_array($option)) {
                return false;
            }

            $label = $option['label'] ?? null;
            $value = $option['value'] ?? null;
            if (!is_string($label) || $label === '' || !(is_string($value) || $this->isFiniteNumber($value))) {
                return false;
            }

            foreach (array_diff_key($option, ['label' => true, 'value' => true]) as $extra) {
                if (!is_string($extra) && !is_bool($extra) && !$this->isFiniteNumber($extra)) {
                    return false;
                }
            }

            if (isset($seen[(string) $value])) {
                return false;
            }
            $seen[(string) $value] = true;
        }

        return true;
    }
}
