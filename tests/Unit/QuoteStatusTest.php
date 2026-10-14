<?php

namespace App\Tests\Unit;

use App\Entity\Quote;
use App\Enum\QuoteStatus;
use PHPUnit\Framework\TestCase;

final class QuoteStatusTest extends TestCase
{
    public function testDraftCanOnlyBeSent(): void
    {
        self::assertTrue(QuoteStatus::Draft->canTransitionTo(QuoteStatus::Sent));
        self::assertFalse(QuoteStatus::Draft->canTransitionTo(QuoteStatus::Accepted));
        self::assertFalse(QuoteStatus::Draft->canTransitionTo(QuoteStatus::Rejected));
    }

    public function testSentCanBeAcceptedOrRejectedButNotReopened(): void
    {
        self::assertTrue(QuoteStatus::Sent->canTransitionTo(QuoteStatus::Accepted));
        self::assertTrue(QuoteStatus::Sent->canTransitionTo(QuoteStatus::Rejected));
        self::assertFalse(QuoteStatus::Sent->canTransitionTo(QuoteStatus::Draft));
    }

    public function testFinalStatusesHaveNoTransition(): void
    {
        self::assertSame([], QuoteStatus::Accepted->allowedTransitions());
        self::assertSame([], QuoteStatus::Rejected->allowedTransitions());
    }

    public function testOnlyDraftIsEditable(): void
    {
        $quote = new Quote();
        self::assertTrue($quote->isEditable());

        $quote->setStatus(QuoteStatus::Sent);
        self::assertFalse($quote->isEditable());
    }

    public function testSentQuoteIsExpiredOnlyAfterValidityDate(): void
    {
        $quote = (new Quote())->setStatus(QuoteStatus::Sent)->setValidUntil(new \DateTimeImmutable('2026-03-10'));

        self::assertFalse($quote->isExpired(new \DateTimeImmutable('2026-03-10 18:00')));
        self::assertTrue($quote->isExpired(new \DateTimeImmutable('2026-03-11 08:00')));
        self::assertFalse($quote->setStatus(QuoteStatus::Accepted)->isExpired(new \DateTimeImmutable('2026-06-01')));
    }
}
