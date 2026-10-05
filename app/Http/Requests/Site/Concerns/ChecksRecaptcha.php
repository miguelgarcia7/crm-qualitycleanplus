<?php

namespace App\Http\Requests\Site\Concerns;

use App\Domain\Marketing\Support\Recaptcha;
use App\Domain\Marketing\Support\RecaptchaAssessment;
use Closure;
use Illuminate\Validation\Validator;

/**
 * Runs the reCAPTCHA check on a public marketing form request once its fields
 * are valid, and keeps the verdict for the controller (the lead email carries
 * it). Rejections land on `g-recaptcha-response`, rendered by
 * resources/views/site/elements/recaptcha.blade.php.
 */
trait ChecksRecaptcha
{
    private ?RecaptchaAssessment $recaptcha = null;

    /** The action the form's script requests a token for. */
    abstract protected function recaptchaAction(): string;

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            // A form going back for other errors gets a fresh token on resubmit;
            // don't spend a Google call on this one.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->recaptcha = app(Recaptcha::class)->assess(
                $this->string('g-recaptcha-response')->toString(),
                $this->recaptchaAction(),
                $this->ip(),
                $this->getHost(),
            );

            if ($this->recaptcha->rejects()) {
                $validator->errors()->add('g-recaptcha-response', __('site/forms.not_a_person'));
            }
        }];
    }

    public function recaptcha(): RecaptchaAssessment
    {
        return $this->recaptcha ?? RecaptchaAssessment::off();
    }
}
