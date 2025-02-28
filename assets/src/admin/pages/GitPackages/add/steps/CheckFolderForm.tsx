import React from 'react';
import { useForm } from 'react-hook-form';
import { __, sprintf } from '@wordpress/i18n';
import {
  Form,
  FormControls,
  FormElement,
  FormFeedback,
  InputSelect,
  InputText,
  NOTICE_TYPES,
} from '../../../../theme';
import { IGitPackageBranch } from '../../../../utils/types';
import { AddRepositoryFormPropsI } from '../AddRepository';

const CheckFolderForm: React.FC<AddRepositoryFormPropsI> = ({
  promise,
  submit,
  repoData,
  className = '',
}) => {
  const [loading, setLoading] = React.useState<boolean>(false);
  const [error, setError] = React.useState<string>('');

  const form = useForm<{
    activeBranch: string;
    repositoryUrl: string;
    dir: string;
  }>({
    defaultValues: {
      repositoryUrl: repoData.baseUrl,
      activeBranch:
        Object.values(repoData.branches).find((branch) => branch.default)
          ?.name || null,
      dir: '',
    },
  });

  return (
    <Form
      onSubmit={form.handleSubmit((data) => {
        setLoading(true);
        promise(data.activeBranch, data.dir)
          .then()
          .catch((e) => setError(e))
          .finally(() => setLoading(false));
      })}
      className={className}
    >
      <FormElement
        form={form}
        name="repositoryUrl"
        label={__('Repository URL', 'shgi')}
        Input={InputText}
        disabled
      />
      <FormElement
        form={form}
        name="activeBranch"
        label={__('Branch', 'shgi')}
        Input={InputSelect}
        options={Object.values(repoData.branches).reduce(
          (acc, branch: IGitPackageBranch) => ({
            ...acc,
            [branch.name]: branch.name,
          }),
          {}
        )}
      />
      <FormElement
        form={form}
        name="dir"
        label={__('Directory', 'shgi')}
        Input={InputText}
        prepend="./"
        prependWidth="0.6em"
        DescriptionInput={
          <p
            dangerouslySetInnerHTML={{
              __html: __(
                "If your Theme or Plugin is in a subdirectory of your repository, specify the path relative to the root path here. Leave empty if it's in the root.",
                'shgi'
              ),
            }}
          />
        }
      />
      {error !== '' && (
        <FormFeedback type={NOTICE_TYPES.ERROR} message={error} />
      )}
      <FormControls
        type="submit"
        loading={loading}
        value={submit}
        align="right"
      />
    </Form>
  );
};

export default CheckFolderForm;
