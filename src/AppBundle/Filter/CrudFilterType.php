<?php
// CrudFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class CrudFilterType
extends AbstractType
{
    protected function addSearchFilter(FormBuilderInterface $builder, array $searchFields, $useFulltext = false)
    {
        $builder->add('search', Filters\TextFilterType::class, [
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) use ($searchFields, $useFulltext)
            {
                if (empty($values['value'])) {
                    return null;
                }

                if ($useFulltext) {
                    // on Innodb, this needs a FULLTEXT index matching the column list
                    $fulltextCondition = \AppBundle\Utils\MysqlFulltextSimpleParser::parseFulltextBoolean($values['value'], true);

                    // requires "beberlei/DoctrineExtensions"
                    $expression = sprintf("MATCH (%s) AGAINST ('%s' BOOLEAN) = TRUE",
                                          implode(', ', $searchFields),
                                          $fulltextCondition);
                    $parameters = [];
                }
                else {
                    $conditions = $parameters = [];

                    // build a matching REGEX
                    $words = preg_split('/\,?\s+/', trim($values['value']));
                    if (count($words) > 0) {
                        $andParts = [];

                        for ($i = 0; $i < count($words); $i++) {
                            if (empty($words[$i])) {
                                continue;
                            }

                            $bindKey = 'regexp' . $i;
                            $parameters[$bindKey] = '[[:<:]]' . $words[$i];

                            $orParts = [];
                            for ($j = 0; $j < count($searchFields); $j++) {
                                // see https://stackoverflow.com/a/29034983/2114681
                                // TODO: use $parameters instead of addslashes
                                $orParts[] = sprintf("REGEXP(%s, :%s) = true",
                                                     $searchFields[$j], $bindKey);
                            }

                            $andParts[] = '(' . implode(' OR ', $orParts) . ')';
                        }

                        if (count($andParts) > 0) {
                            $conditions[] = implode(' AND ', $andParts);
                        }
                    }

                    if (empty($conditions)) {
                        return null;
                    }

                    $expression = join(' AND ', $conditions);
                }

                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection'   => false,
            'validation_groups' => ['filtering'] // avoid NotBlank() constraint-related message
        ]);
    }
}
