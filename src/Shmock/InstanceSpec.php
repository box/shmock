<?php
namespace Shmock;

class InstanceSpec extends StaticSpec
{
    /**
     * @return bool
     */
    protected function isStatic()
    {
        return false;
    }

    /**
     * This is pretty nasty - this is only included for backwards compatibility reasons. Skip
     * checking function args against policies if no args are passed and the relevant flag has
     * been set in Shmock. See the comment on the flag in the Shmock class for more context
     *
     * @return bool
     */
    protected function shouldCheckArgsAgainstPolicies()
    {
        return count($this->arguments) > 0 || Shmock::$check_args_for_policy_on_instance_method_when_no_args_passed;
    }

}
