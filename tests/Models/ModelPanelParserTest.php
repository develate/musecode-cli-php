<?php

declare(strict_types=1);

namespace Develate\MusecodeCli\Tests\Models;

use Develate\MusecodeCli\Models\ModelPanelParser;
use PHPUnit\Framework\TestCase;

final class ModelPanelParserTest extends TestCase
{
    public function test_reads_the_picker_in_order_without_treating_selection_as_a_model_name(): void
    {
        $models = (new ModelPanelParser)->parse("Model set to ignored-model\nChoose model\n\nmuse-spark-1.3\n⟩ muse-spark-1.3-contributor  Your content may be used for product improvement.\nmuse-spark-1.2\nmuse-spark-1.2-contributor  Your content may be used for product improvement.\n↑↓ move · enter confirm · esc go back");

        self::assertSame(['muse-spark-1.3', 'muse-spark-1.3-contributor', 'muse-spark-1.2', 'muse-spark-1.2-contributor'], array_map(static fn ($model) => $model->slug, $models));
        self::assertSame('', $models[0]->description);
        self::assertSame('Your content may be used for product improvement.', $models[1]->description);
    }

    public function test_rejects_output_without_a_model_picker(): void
    {
        self::assertSame([], (new ModelPanelParser)->parse('Model set to muse-spark-1.3'));
        self::assertSame([], (new ModelPanelParser)->parse('Choose model'));
    }
}
