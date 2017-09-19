<?php

namespace RPZ\DiscussionBundle\Repository;

/**
 * CommentRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CommentRepository extends \Doctrine\ORM\EntityRepository
{
  public function whereArticle($id) {
    return $this->createQueryBuilder('e')
        ->innerJoin('e.article', 'i')
        ->where('i.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getResult()
    ;
  }
}
