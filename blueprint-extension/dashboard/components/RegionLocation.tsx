import React, { useEffect, useState } from 'react';
import { faGlobe, faServer } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import StatBlock from '@/components/server/console/StatBlock';
import { ServerContext } from '@/state/server';

interface LocationData {
    status: 'success' | 'local' | 'invalid' | 'rate_limited' | 'unavailable';
    city: string | null;
    country_name: string;
    country_code: string;
    local_ip?: boolean;
    location_source?: 'allocation' | 'alias' | 'sftp_node';
}

export default () => {
    const [location, setLocation] = useState<LocationData | null>(null);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const hasAllocation = ServerContext.useStoreState((state) =>
        state.server.data!.allocations.some((allocation) => allocation.isDefault)
    );
    const flagUrl = location?.country_code
        ? `/extensions/pterodactyllocation/regions/${location.country_code}.svg`
        : null;

    useEffect(() => {
        let active = true;
        setLocation(null);

        if (!hasAllocation) {
            return;
        }

        http.get<LocationData>(`/api/client/extensions/pterodactyllocation/servers/${uuid}/region`, { timeout: 15000 })
            .then(({ data }) => {
                if (active) setLocation(data);
            })
            .catch(() => {
                if (active) {
                    setLocation({
                        status: 'unavailable',
                        city: null,
                        country_name: 'Location unavailable',
                        country_code: '',
                    });
                }
            });

        return () => {
            active = false;
        };
    }, [uuid, hasAllocation]);

    return (
        <div className={'col-span-6 min-w-0'}>
            <StatBlock icon={location?.status === 'local' ? faServer : faGlobe} title={'Region'}>
                <span className={'inline-flex min-w-0 items-center'}>
                    {flagUrl && <img src={flagUrl} alt={''} className={'mr-1 h-4 w-6 flex-shrink-0 rounded-sm object-cover'} />}
                    <span>{!hasAllocation ? 'No allocation' : location ? location.country_name : 'Loading...'}</span>
                    {location?.city && <span className={'ml-1 text-gray-300 text-[70%] select-none'}>, {location.city}</span>}
                </span>
            </StatBlock>
            {location?.local_ip && (
                <p className={'mt-1 px-2 text-xs leading-tight text-gray-300'}>
                    {location.location_source === 'sftp_node'
                        ? 'Private allocation; approximate region from the node SFTP hostname.'
                        : location.location_source === 'alias'
                          ? 'Private allocation; region from its public alias.'
                          : 'Private allocation; no public alias or SFTP location was available.'}
                </p>
            )}
        </div>
    );
};