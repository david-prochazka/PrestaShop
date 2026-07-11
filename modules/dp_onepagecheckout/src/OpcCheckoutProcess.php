<?php
/**
 * One Page Checkout — modern single-page checkout for PrestaShop 9.
 *
 * @author    Integritty
 * @copyright 2026 Integritty
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Integritty\OnePageCheckout;

use CheckoutProcess;
use CheckoutStepInterface;
use Context;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A CheckoutProcess variant tailored for one-page rendering.
 *
 * It reuses the native steps (personal information, addresses, delivery,
 * payment) untouched, so all business logic — validation, persistence,
 * hooks — stays in core. Only two state-machine behaviors change when the
 * "unlock all steps" mode is enabled:
 *
 *  - every step is reachable from the start, so all forms render at once;
 *  - completing/editing an earlier step no longer invalidates later ones,
 *    which would collapse them back to placeholders on a single page.
 */
final class OpcCheckoutProcess extends CheckoutProcess
{
    private bool $unlockAllSteps = false;

    public static function fromCheckoutProcess(
        CheckoutProcess $process,
        Context $context,
        bool $unlockAllSteps
    ): self {
        $opcProcess = new self($context, $process->getCheckoutSession());
        $opcProcess->unlockAllSteps = $unlockAllSteps;

        foreach ($process->getSteps() as $step) {
            /* @var CheckoutStepInterface $step */
            $opcProcess->addStep($step);
        }

        return $opcProcess;
    }

    /**
     * {@inheritdoc}
     *
     * In unlock-all mode every step becomes reachable immediately.
     */
    public function setNextStepReachable()
    {
        if (!$this->unlockAllSteps) {
            return parent::setNextStepReachable();
        }

        foreach ($this->getSteps() as $step) {
            $step->setReachable(true);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * On a single page all steps stay visible, so editing an earlier step
     * must not lock the later ones again in unlock-all mode.
     */
    public function invalidateAllStepsAfterCurrent()
    {
        if (!$this->unlockAllSteps) {
            return parent::invalidateAllStepsAfterCurrent();
        }

        return $this;
    }
}
