<?php
/**
 * This class created for get and save data entity.
 */

namespace core\repositories;

use core\entities\User\User;
use core\dispatchers\EventDispatcher;

class UserRepository
{
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function findByUsernameOrEmail($value): ?User
    {
        return User::find()->andWhere(['or', ['username' => $value], ['email' => $value]])->one();
    }

    public function findByNetworkIdentity($network, $identity): ?User
    {
        return User::find()->joinWith('networks n')->andWhere(['n.network' => $network, 'n.identity' => $identity])->one();
    }

    //TODO i would like created interface repository and implement someone methods into him
    public function get($id): User
    {
        return $this->getBy(['id' => $id]);
    }

    /**
     * @return User[]
     */
    public function getAll(): array
    {
        return User::find()->all();
    }

    public function getByEmailConfirmToken($token): User
    {
        return $this->getBy(['email_confirm_token' => $token]);
    }

    public function getByEmail($email): User
    {
        return $this->getBy(['email' => $email]);
    }

    public function getByPasswordResetToken($token): User
    {
        return $this->getBy(['password_reset_token' => $token]);
    }

    public function existsByPasswordResetToken(string $token): bool
    {
        return (bool) User::findByPasswordResetToken($token);
    }

    /**
     * @param $productId
     * @return iterable|User[]
     */
    public function getAllByProductInWishList($productId): iterable
    {
        return User::find()
            ->alias('u')
            ->joinWith('wishlistItems w', false, 'INNER JOIN')
            ->andWhere(['w.product_id' => $productId])
            ->each();
    }

    public function save(User $user): void
    {
        if (!$user->save()) {
            throw new \RuntimeException('Saving error.');
        }
        $this->dispatcher->dispatchAll($user->releaseEvents());
    }

    public function remove(User $user): void
    {
        if (!$user->delete()) {
            throw new \RuntimeException('Removing error');
        }
        $this->dispatcher->dispatchAll($user->releaseEvents());
    }

    private function getBy(array $condition): User
    {
        if (!$user = User::find()->andWhere($condition)->limit(1)->one()) {
            throw new NotFoundException('User not found.');
        }
        return $user;
    }

    /**
     * Find all users who be in the role by name
     * @param $name
     * @return User[]
     */
    public function findByAuthAssignment($name): array
    {
        $result = User::find()
            ->alias('u')
            ->leftJoin('{{%auth_assignments}} a', 'a.user_id = u.id')
            ->andWhere(['a.item_name' => $name])->all();

        return $result;
    }
}