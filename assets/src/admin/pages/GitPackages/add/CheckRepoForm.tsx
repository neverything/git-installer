import React from 'react';
import { useForm } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import {
  Form,
  FormControls,
  FormElement,
  FormFeedback,
  InputText,
  NOTICE_TYPES,
} from '../../../theme';
import { apiGet } from '../../../utils/apiFetch';
import { VARS } from '../../../utils/constants';
import { IGitPackageRaw } from '../../../utils/types';

const CheckRepoForm: React.FC<{
  setData: (data: IGitPackageRaw) => void;
  repositoryKeys: Array<string>;
}> = ({ setData, repositoryKeys }) => {
  const [loading, setLoading] = React.useState<boolean>(false);
  const [error, setError] = React.useState<string>('');
  const form = useForm<{
    repositoryUrl: string;
  }>({
    defaultValues: {
      repositoryUrl: '',
    },
  });

  return (
    <Form
      onSubmit={form.handleSubmit((data) => {
        setLoading(true);
        apiGet<IGitPackageRaw>(
          VARS.restPluginNamespace +
            '/git-packages-check/' +
            btoa(data.repositoryUrl)
        )
          .then((pkg) => {
            const exists = Boolean(
              repositoryKeys.find((key) => key === pkg.key)
            );
            if (exists) {
              setError(__('Das Repository wurde bereits installiert', 'shgu'));
            } else {
              setData(pkg);
            }
          })
          .catch((e) => setError(e))
          .finally(() => {
            setLoading(false);
          });
      })}
    >
      <FormElement
        form={form}
        name="repositoryUrl"
        label={__('Repository URL', 'shgu')}
        Input={InputText}
        rules={{
          required: __('Das ist ein Pflichtfeld', 'shgu'),
          pattern: {
            value: /^(https:\/\/(github|gitlab|bitbucket)\.\S+)/,
            message: __(
              'Die URL muss zu einem Github, Gitlab oder Bitbucket Repository führen',
              'shgu'
            ),
          },
        }}
      />
      {error !== '' && (
        <FormFeedback type={NOTICE_TYPES.ERROR}>{error}</FormFeedback>
      )}
      <FormControls
        type="submit"
        loading={loading}
        value={__('URL überpfüfen', 'shgu')}
      />
    </Form>
  );
};

export default CheckRepoForm;
